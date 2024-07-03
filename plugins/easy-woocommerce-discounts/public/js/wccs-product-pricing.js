(function ($) {
  "use strict";

  /**
   * All of the code for your public-facing JavaScript source
   * should reside in this file.
   *
   * Note: It has been assumed you will write jQuery code here, so the
   * $ function reference has been prepared for usage within the scope
   * of this function.
   *
   * This enables you to define handlers, for when the DOM is ready:
   *
   * $(function() {
   *
   * });
   *
   * When the window is loaded:
   *
   * $( window ).load(function() {
   *
   * });
   *
   * ...and/or other possibilities.
   *
   * Ideally, it is not considered best practise to attach more than a
   * single DOM-ready or window-load handler for a particular page.
   * Although scripts in the WordPress core, Plugins and Themes may be
   * practising this, we should strive to set a better example in our own work.
   */

  /**
   * ProductPricing class which handles discounted prices.
   *
   * @since  1.0.0
   */
  var ProductPricing = function () {
    // Methods.
    this.init = this.init.bind(this);
    this.onFoundVariation = this.onFoundVariation.bind(this);
    this.onHideVariation = this.onHideVariation.bind(this);

    this.init();
  };

  /**
   * Initialize.
   */
  ProductPricing.prototype.init = function () {
    if ($(".variations_form").length) {
      (this.$bulkTables = $(".wccs-bulk-pricing-table-container")),
        (this.$bulkTitles = $(".wccs-bulk-pricing-table-title"));
      (this.$parentTable = this.$bulkTables.not("[data-variation]")),
        (this.$parentTableTitle = this.$bulkTitles.not("[data-variation]"));
      this.$variationForm = $(".variations_form");
      $(document.body).on(
        "found_variation.wccs_product_pricing",
        this.$variationForm,
        this.onFoundVariation
      );
      $(document.body).on(
        "hide_variation.wccs_product_pricing",
        this.$variationForm,
        this.onHideVariation
      );
    }

    this.$cartForm = $(".product form.cart");
  };

  /**
   * Handler function execute when WooCommerce found_variation triggered.
   *
   * @since  1.0.0
   *
   * @param  event
   * @param  variation
   *
   * @return void
   */
  ProductPricing.prototype.onFoundVariation = function (event, variation) {
    // Bulk pricing table.
    if (this.$bulkTables.length) {
      this.$bulkTables.hide();
      this.$bulkTitles.hide();
      if (
        this.$bulkTables.filter(
          '[data-variation="' + variation.variation_id + '"]'
        ).length
      ) {
        this.$bulkTables
          .filter('[data-variation="' + variation.variation_id + '"]')
          .show();
        this.$bulkTitles
          .filter('[data-variation="' + variation.variation_id + '"]')
          .show();
      } else if (this.$parentTable.length) {
        this.$parentTable.show();
        this.$parentTableTitle.show();
      }
    }
  };

  /**
   * Handler function execute when WooCommerce hide_variation triggered.
   *
   * @since  1.0.0
   *
   * @param  event
   *
   * @return void
   */
  ProductPricing.prototype.onHideVariation = function (event) {
    // Bulk pricing table.
    if (this.$bulkTables.length) {
      this.$bulkTables.hide();
      this.$bulkTitles.hide();
      if (this.$parentTable.length) {
        this.$parentTable.show();
        this.$parentTableTitle.show();
      }
    }
  };

  /**
   * Creating a singleton instance of ProductPricing.
   */
  var Singleton = (function () {
    var instance;

    return {
      getInstance: function () {
        if (!instance) {
          instance = new ProductPricing();
        }
        return instance;
      },
    };
  })();

  $.fn.wccs_get_product_pricing = function () {
    return Singleton.getInstance();
  };

  $(function () {
    $().wccs_get_product_pricing();
  });
})(jQuery);
