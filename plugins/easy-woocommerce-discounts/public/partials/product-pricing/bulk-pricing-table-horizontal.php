<?php
// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! empty( $view_args['table_title'] ) ) {
	echo '<p class="wccs-bulk-pricing-table-title"' . ( ! empty( $view_args['variation'] ) ? ' data-variation="' . esc_attr( $view_args['variation'] ) . '"' : '' ) . ' style="' . ( ! empty( $view_args['variation'] ) ? 'display:none' : '' ) . '"><strong>' . esc_html( $view_args['table_title'] ) . '</strong></p>';
}
?>
<div class="wccs-bulk-pricing-table-container" <?php echo ! empty( $view_args['variation'] ) ? 'data-variation="' . esc_attr( $view_args['variation'] ) . '"' : '' ?> style="<?php echo ! empty( $view_args['variation'] ) ? 'display:none' : ''; ?>">
	<table class="wccs-bulk-pricing-table wccs-horizontal-table" <?php echo ! empty( $view_args['variation'] ) ? 'data-variation="' . esc_attr( $view_args['variation'] ) . '"' : '' ?>>
		<tbody>
			<?php if ( 'yes' === $view_args['discount']['display_quantity'] ) { ?>
				<tr>
					<th><?php echo esc_html( $view_args['quantity_label'] ) ?></th>
					<?php
					foreach ( $view_args['discount']['quantities'] as $discount ) {
						echo '<th>' . esc_html( apply_filters( 'wccs_quantity_table_quantity', $discount['min'] . ( ! empty( $discount['max'] ) ? ( $discount['min'] != $discount['max'] ? ' - ' . $discount['max'] : '' ) : ' +' ) ) ) . '</th>';
					}
					?>
				</tr>
			<?php } ?>
			<?php if ( 'yes' === $view_args['discount']['display_discount'] ) { ?>
				<tr>
					<td><?php echo esc_html( $view_args['discount_label'] ) ?></td>
					<?php
					foreach ( $view_args['discount']['quantities'] as $discount ) {
						echo '<td>' . apply_filters( 'wccs_quantity_table_discount', $view_args['controller']->get_discount_value_html( $discount['discount'], $discount['discount_type'] ) ) . '</td>';
					}
					?>
				</tr>
			<?php } ?>
			<?php if ( 'yes' === $view_args['discount']['display_price'] ) { ?>
				<tr>
					<td><?php echo esc_html( $view_args['price_label'] ) ?></td>
					<?php
					foreach ( $view_args['discount']['quantities'] as $discount ) {
						echo '<td>' . apply_filters( 'wccs_quantity_table_price', $view_args['controller']->get_discounted_price( $discount['discount'], $discount['discount_type'] ) ) . '</td>';
					}
					?>
				</tr>
			<?php } ?>
		</tbody>
	</table>
</div>
