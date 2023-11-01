/**
 * Some copyright header.
 * Goes here
 *
 *
 * foobar
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
    function the_function_names_are_different(config) {
        // this has been overridden and the function is different now
        var foo = 1;
    }

    /**
     * Some other function comment
     *
     * @param {Object} config - Optional configuration
     */
    function init(config) {
        resolver(the_function_names_are_different.bind(config));
    }

    return init;
});