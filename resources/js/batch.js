CRM.$(function($) {
    var container = $(document.getElementsByClassName('gift-aid'));

    /**
     * Base class
     *
     * @type {Object}
     */
    var Base = {
        /**
         * @param config
         * @returns {Object}
         */
        create: function (config) {
            var instance = Object.create(this);
            instance.config = config || {};

            return instance;
        },
        /**
         * Setup
         */
        setup: function () {
            this.configure();
            this.init();
            this.createBindings();
        },
        /**
         * Set variables and other configurations to be used in the later stages
         */
        configure: function () {
        },
        /**
         * Initialise
         */
        init: function () {
        },
        /**
         * Create bindings
         */
        createBindings: function () {
        }
    };

    /**
     * Batch operations class
     */
    var BatchOperations = Base.create();

    BatchOperations.configure = function (config) {
        Base.configure(config);

        this.contributions = container.find('.contribution');
        this.lineItems = container.find('.line-items');
    };

    BatchOperations.init = function () {
        this.contributions.addClass('collapsed');
        this.lineItems.toggle();
    };

    BatchOperations.createBindings = function () {
        this.contributions.on('click', function () {
            var contribution = cj(this);
            var contributionId = contribution.data('contribution-id');
            var financialItems = cj('#line-items-' + contributionId);

            contribution.toggleClass('collapsed');

            if (contribution.hasClass('collapsed')) {
                financialItems.fadeOut(100);
            } else {
                financialItems.fadeIn(100);
            }
        });
    };

    BatchOperations.setup();
});