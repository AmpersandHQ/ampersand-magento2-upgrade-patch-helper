/**
 * Some copyright header.
 * Goes here
 */
define([
    'abc123'
], function (resolver) {
    'use strict';


    // more comments
    /*
     like this
     */
    /**
     * Some function comment.
     *
     * @param {Object} config - Optional configuration
     */
    function foobar(config) {
        var foo = 1;
    }

    // more comments

    /**
     * Some other function comment
     *
     * @param {Object} config - Optional configuration
     */
    function init(config) {
        resolver(foobar.bind(config));
    }

    return init;
});