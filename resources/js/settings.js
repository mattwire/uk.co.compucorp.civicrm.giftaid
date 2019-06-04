CRM.$(function ($) {
    var container = $(document.getElementsByClassName('CRM_Civigiftaid_Form_Settings'));

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
     * Settings class
     */
    var Settings = Base.create();

    Settings.configure = function () {
        this.financialTypes = container.find('#s2id_financial_types_enabled').parent('td');
        this.globallyEnabled = container.find('#globally_enabled');
    };

    Settings.init = function () {
        if (this.globallyEnabled.prop('checked')) {
            this.toggleFinancialTypesDisplay();
        }
    };

    Settings.createBindings = function () {
        var self = this;

        this.globallyEnabled.on('click', function () {
            self.toggleFinancialTypesDisplay();
        });
    };

    Settings.toggleFinancialTypesDisplay = function () {
        this.financialTypes.toggle();
    };

    Settings.setup();
});