<?php
/**
 * Server-Side Render template for wm/event-grid
 *
 * Lists the active events of WM Cyber Events Core, each with a link to its event page. Until 1.2.2
 * that link read "Jetzt verbindlich buchen" (a click books nothing) and fell back to #booking, an
 * anchor nothing defined, and the empty state told visitors to insert event-card blocks, which the
 * editor of this block cannot hold.
 *
 * @var array<string, mixed> $attributes
 * @var string               $content
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Until 1.2.2 a categoryFilter setting filtered on a `category` field the events table of WM Cyber
// Events Core does not have, so the control changed nothing; it is gone.
$has_events_plugin = class_exists( 'CyberEvents_Database' ) && method_exists( 'CyberEvents_Database', 'get_active_events' );
$columns           = max( 1, (int) ( $attributes['columns'] ?? 3 ) );
$limit             = max( 1, (int) ( $attributes['limit'] ?? 6 ) );

$active_events = [];
if ( '' === trim( $content ) && class_exists( 'CyberEvents_Database' ) && method_exists( 'CyberEvents_Database', 'get_active_events' ) ) {
	$raw_events = \CyberEvents_Database::get_active_events();
	if ( is_array( $raw_events ) ) {
		foreach ( $raw_events as $event_row ) {
			$event_obj = is_array( $event_row ) ? (object) $event_row : $event_row;
			if ( ! is_object( $event_obj ) ) {
				continue;
			}
			$ev_title = sanitize_text_field( (string) ( $event_obj->title ?? '' ) );
			if ( '' === $ev_title ) {
				continue;
			}
			$active_events[] = $event_obj;
			if ( count( $active_events ) >= $limit ) {
				break;
			}
		}
	}
}

if ( '' === trim( $content ) && empty( $active_events ) ) {
	if ( ! $has_events_plugin ) {
		echo wp_kses_post( wm_suite_hub_missing_plugin_notice( 'WM Cyber Events Core' ) );
	} else {
		/* translators: %s: name of the events plugin */
		$no_events = __( '„%s“ meldet keine aktiven Veranstaltungen; Besucher sehen den Block bis dahin nicht.', 'wm-suite-hub' );
		echo wp_kses_post( wm_suite_hub_block_notice( sprintf( $no_events, 'WM Cyber Events Core' ) ) );
	}
	return;
}
?>
<section class="wm-block-container wm-event-grid-container" style="--wm-grid-cols: <?php echo esc_attr( (string) $columns ); ?>;">
	<div class="wm-event-grid">
		<?php if ( '' !== trim( $content ) ) : ?>
			<?php echo wp_kses_post( $content ); ?>
		<?php elseif ( ! empty( $active_events ) ) : ?>
			<?php foreach ( $active_events as $event_obj ) : ?>
				<?php
				$ev_title    = sanitize_text_field( (string) ( $event_obj->title ?? '' ) );
				$ev_category = sanitize_text_field( (string) ( $event_obj->category ?? '' ) );
				$ev_location = sanitize_text_field( (string) ( $event_obj->location ?? '' ) );
				$ev_date_raw = sanitize_text_field( (string) ( $event_obj->start_date ?? '' ) );
				$ev_date     = '' !== $ev_date_raw ? substr( $ev_date_raw, 0, 10 ) : '';
				$ev_price    = '';
				if ( ! empty( $event_obj->is_paid ) && isset( $event_obj->ticket_price ) && is_numeric( $event_obj->ticket_price ) && (float) $event_obj->ticket_price > 0 ) {
					$ev_price = number_format( (float) $event_obj->ticket_price, 2, ',', '.' ) . ' €';
				}
				$ev_url = '';
				if ( ! empty( $event_obj->wp_post_id ) && function_exists( 'get_permalink' ) ) {
					$permalink = get_permalink( (int) $event_obj->wp_post_id );
					if ( is_string( $permalink ) && '' !== $permalink ) {
						$ev_url = $permalink;
					}
				}
				$ev_remaining = isset( $event_obj->remaining_capacity ) && is_numeric( $event_obj->remaining_capacity )
					? (int) $event_obj->remaining_capacity
					: null;
				$ev_max_cap   = isset( $event_obj->max_capacity ) && is_numeric( $event_obj->max_capacity )
					? (int) $event_obj->max_capacity
					: 0;
				?>
				<article class="wm-event-card">
					<div>
						<?php if ( '' !== $ev_category ) : ?>
							<span class="wm-event-card__badge"><?php echo esc_html( $ev_category ); ?></span>
						<?php endif; ?>
						<h3 class="wm-event-card__title"><?php echo esc_html( $ev_title ); ?></h3>
						<?php if ( '' !== $ev_location || '' !== $ev_date || '' !== $ev_price ) : ?>
							<div class="wm-event-card__meta">
								<?php if ( '' !== $ev_location ) : ?>
									<span><?php echo wp_kses( wm_suite_hub_icon( 'location' ), wm_suite_hub_icon_allowed_html() ); ?> <?php echo esc_html( $ev_location ); ?></span>
								<?php endif; ?>
								<?php if ( '' !== $ev_date ) : ?>
									<span><?php echo wp_kses( wm_suite_hub_icon( 'calendar' ), wm_suite_hub_icon_allowed_html() ); ?> <?php echo esc_html( $ev_date ); ?></span>
								<?php endif; ?>
								<?php if ( '' !== $ev_price ) : ?>
									<span><?php echo wp_kses( wm_suite_hub_icon( 'tag' ), wm_suite_hub_icon_allowed_html() ); ?> <?php echo esc_html( $ev_price ); ?></span>
								<?php endif; ?>
							</div>
						<?php endif; ?>
						<?php if ( null !== $ev_remaining ) : ?>
							<?php
							$dot_class = 'wm-capacity-dot';
							if ( $ev_remaining <= 0 ) {
								$dot_class .= ' wm-capacity-dot--empty';
								$cap_text   = __( 'Ausgebucht', 'wm-suite-hub' );
							} elseif ( $ev_remaining < 5 ) {
								$dot_class .= ' wm-capacity-dot--low';
								/* translators: %d: remaining seats */
								$low_text = __( 'Nur noch %d Plätze', 'wm-suite-hub' );
								$cap_text = sprintf( $low_text, $ev_remaining );
							} else {
								/* translators: %d: remaining seats */
								$free_text = __( '%d freie Plätze', 'wm-suite-hub' );
								$cap_text  = sprintf( $free_text, $ev_remaining );
							}
							?>
							<div class="wm-event-card__capacity">
								<span class="<?php echo esc_attr( $dot_class ); ?>"></span>
								<span><?php echo esc_html( $cap_text ); ?></span>
							</div>
						<?php elseif ( $ev_max_cap > 0 ) : ?>
							<div class="wm-event-card__capacity">
								<span class="wm-capacity-dot"></span>
								<span>
									<?php
									/* translators: %d: maximum total capacity */
									$max_text = __( 'Max. %d Plätze', 'wm-suite-hub' );
									echo esc_html( sprintf( $max_text, $ev_max_cap ) );
									?>
								</span>
							</div>
						<?php endif; ?>
					</div>
					<?php if ( '' !== $ev_url ) : ?>
						<a href="<?php echo esc_url( $ev_url ); ?>" class="wm-btn-primary"><?php esc_html_e( 'Zur Veranstaltung', 'wm-suite-hub' ); ?></a>
					<?php endif; ?>
				</article>
			<?php endforeach; ?>
		<?php endif; ?>
	</div>
</section>
