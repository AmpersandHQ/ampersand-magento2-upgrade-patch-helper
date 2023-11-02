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
    function foobar(config) {
        var foo = 1;
    }


    /**
     * what comments // do we have here
     * /*
     *
     *    oh what comments are here
     * /
     */


    /**
     * Some other function comment
     *
     * @param {Object} config - Optional configuration
     */
    function init(config) {
                resolver(foobar.bind(config));
    }
    // more comments

    return init;
});