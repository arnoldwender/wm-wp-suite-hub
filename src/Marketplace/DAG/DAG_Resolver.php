<?php
/**
 * Class DAG_Resolver
 *
 * Implements Kahn's Topological Sort algorithm over a Directed Acyclic Graph (DAG)
 * to compute the deterministic installation and bootstrap sequence of packages
 * while detecting circular dependencies.
 *
 * @package ArnoldWender\SuiteHub\Marketplace\DAG
 * @since   1.0.0
 */

declare(strict_types=1);

namespace ArnoldWender\SuiteHub\Marketplace\DAG;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class DAG_Resolver {

    /**
     * Resolves the installation order for a collection of packages based on dependencies.
     *
     * @param array<string, array<string>> $dependency_graph Map of [package_slug => [dependency_slugs]].
     * @return array<int, string> Ordered list of package slugs (independent nodes first).
     * @throws \DomainException If a circular dependency (cycle) is detected.
     */
    public static function resolve_order(array $dependency_graph): array {
        if (empty($dependency_graph)) {
            return [];
        }

        // Ensure all mentioned dependencies exist in the graph
        $graph = $dependency_graph;
        foreach ($dependency_graph as $slug => $deps) {
            foreach ($deps as $dep) {
                if (!isset($graph[$dep])) {
                    $graph[$dep] = [];
                }
            }
        }

        // Calculate in-degrees (number of unmet prerequisites for each node)
        $in_degree = [];
        $adjacency = []; // Parent -> Children (who depends on Parent)

        foreach ($graph as $node => $deps) {
            $in_degree[$node] = 0;
            $adjacency[$node] = [];
        }

        foreach ($graph as $node => $deps) {
            $unique_deps = array_unique($deps);
            $in_degree[$node] = count($unique_deps);

            foreach ($unique_deps as $dep) {
                $adjacency[$dep][] = $node;
            }
        }

        // Queue of nodes with in-degree 0 (no prerequisites)
        $queue = [];
        foreach ($in_degree as $node => $degree) {
            if ($degree === 0) {
                $queue[] = $node;
            }
        }

        // Deterministic sort for nodes at the same priority level
        sort($queue, SORT_STRING);

        $resolved_order = [];

        while (!empty($queue)) {
            $current = array_shift($queue);
            $resolved_order[] = $current;

            $next_level = [];
            foreach ($adjacency[$current] as $dependent_node) {
                $in_degree[$dependent_node]--;
                if ($in_degree[$dependent_node] === 0) {
                    $next_level[] = $dependent_node;
                }
            }

            if (!empty($next_level)) {
                sort($next_level, SORT_STRING);
                foreach ($next_level as $item) {
                    $queue[] = $item;
                }
            }
        }

        // Cycle check: If resolved order does not cover all nodes, a cycle exists
        if (count($resolved_order) !== count($graph)) {
            $unresolved = array_diff(array_keys($graph), $resolved_order);
            throw new \DomainException(
                sprintf(
                    'Zirkuläre Abhängigkeit im Paketgraphen entdeckt. Ungelöste Knoten: %s',
                    esc_html(implode(', ', $unresolved))
                )
            );
        }

        return $resolved_order;
    }
}
