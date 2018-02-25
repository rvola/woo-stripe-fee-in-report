<?php
/**
 * Class report that displays the Stripe fees.
 *
 * @package RVOLA\WOO\SFR
 **/

namespace RVOLA\WOO\SFR;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
if ( ! defined( 'WC_ABSPATH' ) ) {
	exit;
}
require_once WC_ABSPATH . 'includes/admin/reports/class-wc-admin-report.php';

/**
 * Class Report
 *
 * @package RVOLA\WOO\SFR
 */
class Report extends \WC_Admin_Report {

	/**
	 * Color for the graph.
	 */
	const COLOR = '#572ff8';
	/**
	 * Name of the meta key for Stripe fees.
	 */
	const META_NAME_FEE = 'Stripe Fee';

	/**
	 * Chart data table.
	 *
	 * @var $chart_data
	 */
	private $chart_data;
	/**
	 * Data table to build the graph.
	 *
	 * @var $report_data
	 */
	private $report_data;

	/**
	 * WooSFR constructor.
	 */
	public function __construct() {
		$this->init_variable();
		$this->init_widget_dashboard();

		add_filter( 'woocommerce_admin_report_data', array( $this, 'add_report_data' ), 10, 1 );
		add_filter( 'woocommerce_reports_get_order_report_query', array( $this, 'clean_query_get_order' ), 10, 1 );

		add_filter( 'woocommerce_admin_report_chart_data', array( $this, 'chart_add_report_data' ), 10, 1 );
		add_action( 'admin_print_footer_scripts', array( $this, 'chart_add_legend' ), 10 );
		add_action( 'admin_print_footer_scripts', array( $this, 'chart_update_legend_placeholder' ), 10 );
		add_action( 'admin_print_footer_scripts', array( $this, 'chart_draw' ), 10 );
		add_action( 'admin_print_footer_scripts', array( $this, 'chart_style' ), 10 );

	}

	/**
	 * Set the default values to start the class.
	 */
	private function init_variable() {

		$current_range = ! empty( $_GET['range'] ) ? sanitize_text_field( wp_unslash( $_GET['range'] ) ) : '7day';

		if ( ! in_array( $current_range, array( 'custom', 'year', 'last_month', 'month', '7day' ), true ) ) {
			$current_range = '7day';
		}

		$this->check_current_range_nonce( $current_range );
		$this->calculate_current_range( $current_range );
	}

	/**
	 * Adjust the "total net" value in the widget on the dashboard.
	 */
	private function init_widget_dashboard() {

		global $pagenow;

		if ( 'index.php' === $pagenow ) {
			$this->start_date = strtotime( date( 'Y-m-01', current_time( 'timestamp' ) ) );
			$this->end_date   = current_time( 'timestamp' );
		}

	}

	/**
	 * Add Stripes fees to the chart data table.
	 *
	 * @param array $report_data Data table.
	 *
	 * @return array $this->_report_data
	 */
	public function add_report_data( $report_data ) {

		// Set report_data in class.
		$this->report_data = $report_data;

		// Search Stripe fees values.
		$stripe_fees = $this->get_order_report_data( array(
			'data'         => array(
				'___stripe_fee___' => array(
					'type'     => 'meta',
					'function' => 'SUM',
					'name'     => 'total',
				),
				'post_date'        => array(
					'type'     => 'post_data',
					'function' => '',
					'name'     => 'post_date',
				),
			),
			'group_by'     => $this->group_by_query,
			'order_by'     => 'post_date ASC',
			'query_type'   => 'get_results',
			'filter_range' => true,
			'order_types'  => wc_get_order_types( 'sales-reports' ),
			'order_status' => array( 'completed', 'processing', 'on-hold', 'refunded' ),
		) );

		// Add fees in Report Data.
		$this->report_data->stripe_fees = $stripe_fees;

		// Add Total Stripe fees.
		$this->report_data->total_stripe_fees = wc_format_decimal( array_sum( wp_list_pluck( $this->report_data->stripe_fees, 'total' ) ), 2 );

		// Update Net Sale - Stripe fees.
		$this->report_data->net_sales = wc_format_decimal( $this->report_data->total_sales - $this->report_data->total_shipping - $this->report_data->total_stripe_fees - max( 0, $this->report_data->total_tax ) - max( 0, $this->report_data->total_shipping_tax ) - max( 0, $this->report_data->total_stripe_fees ), 2 );

		return $this->report_data;
	}

	/**
	 * Unfortunately, the post_meta that stores the Stripe Fee uses a space.
	 * With this method, the query is modified to be operational.
	 *
	 * @param array $query Request.
	 *
	 * @return array $query
	 */
	public function clean_query_get_order( $query ) {

		preg_match( '/___stripe_fee___/', $query['select'], $match );
		if ( $match && is_array( $query ) ) {
			$query['select'] = str_replace( '___stripe_fee___', 'key', $query['select'] );
			$query['join']   = str_replace( 'meta____stripe_fee___', 'meta_key', $query['join'] );
			$query['join']   = str_replace( "'___stripe_fee___'", "'" . self::META_NAME_FEE . "'", $query['join'] );
		}

		return $query;
	}

	/**
	 * Added query results in the chart.
	 *
	 * @param array $data Data table.
	 *
	 * @return array $this->chart_data
	 */
	public function chart_add_report_data( $data ) {

		// Set $data in class.
		$this->chart_data = $data;

		// Add Stripe fees in data graph array.
		$this->chart_data['stripe_fees'] = $this->prepare_chart_data( $this->report_data->stripe_fees, 'post_date', 'total', $this->chart_interval, $this->start_date, $this->chart_groupby );

		// Update Net Sale - Stripe fees.
		foreach ( $this->chart_data['net_order_amounts'] as $order_amount_key => $order_amount_value ) {
			$this->chart_data['net_order_amounts'][ $order_amount_key ][1] -= $this->chart_data['stripe_fees'][ $order_amount_key ][1];
		}

		return $this->chart_data;
	}

	/**
	 * Add the new legend in chart.
	 */
	public function chart_add_legend() {

		if ( ! $this->chart_data ) {
			return;
		}
		$link = sprintf(
			'<li style="border-color: %1$s" class="highlight_series" data-series="%2$d">%3$s</li>',
			self::COLOR,
			$this->get_serie( 'stripe_fees' ),
			sprintf(
			    // translators: %s is Stripe fees.
				_x( '%s Stripe fees', 'number', 'woo-stripe-fee-in-report' ),
				'<strong>' . wc_price( $this->report_data->total_stripe_fees ) . '</strong>'
			)
		);
		?>

        <script type="text/javascript">
            jQuery('ul.chart-legend').append('<?php echo $link; ?>');
        </script>

		<?php

	}

	/**
	 * Retrieves the key number of the table to add the values to the chart.
	 *
	 * @param string $name The name of the key to search for.
	 *
	 * @return int
	 */
	private function get_serie( $name ) {
		if ( ! $this->chart_data ) {
			return false;
		}

		return array_search( $name, array_keys( $this->chart_data ), true ) - 1;
	}

	/**
	 * Replaces hover text for legend.
	 * In our case, I use it to change the text concerning the net commands taking into account the Stripes fees.
	 */
	public function chart_update_legend_placeholder() {
		$update_legend = array(
			$this->get_serie( 'net_order_amounts' ) => __( 'This is the sum of the order totals after any refunds and excluding shipping, taxes and Stripe fees.', 'woo-stripe-fee-in-report' ),
		);

		echo '<script type="text/javascript">';
		foreach ( $update_legend as $serie => $text ) {
			printf(
				"jQuery('ul.chart-legend').find('li[data-series=%d]').attr('data-tip', '%s');",
				intval( $serie ),
				esc_js( $text )
			);
		}
		echo '</script>';
	}

	/**
	 * Change the size of the graph to be in harmony with the legend.
	 */
	public function chart_style() {

		echo '<script type="text/javascript">';
		echo 'jQuery(".woocommerce-reports-wide .postbox .chart-placeholder").height(jQuery("ul.chart-legend").height() - 25);';
		echo '</script>';
	}

	/**
	 * There is a way to add data to jQuery.plot, but it is almost useless if you change the graph options.
	 * The ideal would probably be a hook directly in WooCommerce.
	 * Much of the code comes from the WC_Report_Sales_By_Date get_main_chart() method
	 */
	public function chart_draw() {

		global $wp_locale;

		if ( ! $this->chart_data ) {
			return;
		}

		$stripe_fees = wp_json_encode(
			array_map( array( $this, 'round_chart_totals' ), array_values( $this->chart_data['stripe_fees'] ) )
		);

		?>

        <script type="text/javascript">

            jQuery(function () {

                var stripe_main_char;

                var drawGraph = function (highlight) {

                    // Get Data.
                    var series = [];
                    var current_series = main_chart.getData();
                    jQuery(current_series).each(function (i, value) {

                        // Change Yaxis shipping.
                        if (value.label === '<?php echo esc_js( __( 'Shipping amount', 'woocommerce' ) ) ?>') {
                            value.yaxis = 3;
                        }

                        series.push(value);
                    });

                    // Stripe serie.
                    var stripe_series = {
                        label: "<?php echo esc_js( __( 'Stripe fees', 'woo-stripe-fee-in-report' ) ) ?>",
                        data: <?php echo $stripe_fees;?>,
                        yaxis: 3,
                        color: '<?php echo self::COLOR; ?>',
                        points: {show: true, radius: 5, lineWidth: 2, fillColor: '#fff', fill: true},
                        lines: {show: true, lineWidth: 2, fill: false},
                        shadowSize: 0,
						<?php echo $this->get_currency_tooltip(); ?>
                    };
                    series.push(stripe_series);

                    if (highlight !== 'undefined' && series[highlight]) {
                        highlight_series = series[highlight];

                        highlight_series.color = '#9c5d90';

                        if (highlight_series.bars) {
                            highlight_series.bars.fillColor = '#9c5d90';
                        }

                        if (highlight_series.lines) {
                            highlight_series.lines.lineWidth = 5;
                        }
                    }

                    stripe_main_char = jQuery.plot(
                        jQuery('.chart-placeholder.main'),
                        series,
                        {
                            legend: {
                                show: false
                            },
                            grid: {
                                color: '#aaa',
                                borderColor: 'transparent',
                                borderWidth: 0,
                                hoverable: true
                            },
                            xaxes: [{
                                color: '#aaa',
                                position: "bottom",
                                tickColor: 'transparent',
                                mode: "time",
                                timeformat: "<?php echo ( 'day' === $this->chart_groupby ) ? '%d %b' : '%b'; ?>",
                                monthNames: <?php echo json_encode( array_values( $wp_locale->month_abbrev ) ) ?>,
                                tickLength: 1,
                                minTickSize: [1, "<?php echo $this->chart_groupby; ?>"],
                                font: {
                                    color: "#aaa"
                                }
                            }],
                            yaxes: [
                                {
                                    min: 0,
                                    minTickSize: 1,
                                    tickDecimals: 0,
                                    color: '#d4d9dc',
                                    font: {color: "#aaa"}
                                },
                                {
                                    position: "right",
                                    min: 0,
                                    tickDecimals: 2,
                                    alignTicksWithAxis: 1,
                                    color: 'transparent',
                                    font: {color: "#aaa"}
                                },
                                {
                                    position: "right",
                                    min: 0,
                                    tickDecimals: 2,
                                    alignTicksWithAxis: 1,
                                    color: 'transparent',
                                    font: {color: "#aaa"},
                                    autoscaleMargin: 5
                                }
                            ],
                        }
                    );
                    jQuery('.chart-placeholder').resize();
                }

                drawGraph();

                jQuery('.highlight_series').hover(
                    function () {
                        drawGraph(jQuery(this).data('series'));
                    },
                    function () {
                        drawGraph();
                    }
                );

            });
        </script>
		<?php
	}

	/**
	 * Method from the 'WC_Report_Taxes_By_Date' file required for the construction of the graph
	 *
	 * @param array|string $amount value to transform.
	 *
	 * @return array|string
	 */
	private function round_chart_totals( $amount ) {

		if ( is_array( $amount ) ) {
			return array( $amount[0], wc_format_decimal( $amount[1], wc_get_price_decimals() ) );
		} else {
			return wc_format_decimal( $amount, wc_get_price_decimals() );
		}
	}

}
