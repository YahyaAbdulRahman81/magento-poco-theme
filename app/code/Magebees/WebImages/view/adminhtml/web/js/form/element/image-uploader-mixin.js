/**
 * Copyright © 2021 Magebees. All rights reserved.
 * See LICENSE.txt for license details.
 */

define(function () {
    'use strict';

    return function (imageUploader) {
        return imageUploader.extend({
            initialize: function () {
                this._super();

                if (typeof this.allowedExtensions === 'string') {
                    this.allowedExtensions += ' svg';
                    this.allowedExtensions += ' webp';
                }
            }
        });
    };
});
