/**
 * Some copyright header.
 * Goes here
 */
define([
    'abc123'
], function (resolver) {
    'use strict';

    /**
     * Some function comment.
     *
     * @param {Object} config - Optional configuration
     */
    function this_is_the_base_function_name(config) {
        var foo = 1;
    }

    /**
     * Some other function comment
     *
     * @param {Object} config - Optional configuration
     */
    function init(config) {
        resolver(this_is_the_base_function_name.bind(config));
    }

    return init;
});