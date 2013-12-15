/**
 * Request.JSON - extended to get some informations about calls and creates info for user if a call fails.
 */
Request.JSON = new Class({
    Extends: Request.JSON,

    initialize: function (options) {
        if (!'secure' in options) {
            options.secure = true;
        }
        this.addEvent('complete', this.checkError.bind(this));
        this.addEvent('error', this.invalidJson.bind(this));

        this.addEvent('complete', function (pData) {
            window.fireEvent('restCall', [pData, this]);
        }.bind(this));

        if (options.progressButton) {
            this.addEvent('progress', function(event) {
                options.progressButton.setProgress(parseInt(event.loaded / event.total * 100));
            });
        }

        this.parent(options);

        if (options.noErrorReporting === true) {
            return;
        }

    },

    onFailure: function () {
        var text = this.response.text;
        var json;

        try {
            json = this.response.json = JSON.decode(text, this.options.secure);
        } catch (error) {
            this.fireEvent('error', [text, error]);
            return;
        }

        var args = [json, this.response.xml];
        this.fireEvent('complete', args).fireEvent('failure', [this.xhr, args[0], args[1]]);
    },

    invalidJson: function () {
        if (ka.lastRequestBubble) {
            ka.lastRequestBubble.die();
            delete ka.lastRequestBubble;
        }

        this.fireEvent('failure');

        if (!ka.adminInterface || !ka.adminInterface.getHelpSystem()) {
            return false;
        }

        ka.lastRequestBubble = ka.adminInterface.getHelpSystem().newBubble(
            t('Response error'),
            t('Server\'s response is not valid JSON. Looks like the server has serious troubles. :-(') +
                "<br/>" + 'URI: %s'.replace('%s', this.options.url) +
                '<br/><a class="ka-Button" href="javascript:;">Details</a>',
            15000);
        throw 'Response Error %s'.replace('%s', this.options.url);
    },

    checkError: function (result) {
        if (true !== this.options.noErrorReporting && result && result.error) {

            if (typeOf(this.options.noErrorReporting) == 'array' &&
                this.options.noErrorReporting.contains(result.error)) {
                return false;
            }

            if (true === this.options.noErrorReporting) {
                return false;
            }

            if (ka.lastRequestBubble) {
                ka.lastRequestBubble.die();
                delete ka.lastRequestBubble;
            }

            if (!ka.adminInterface || !ka.adminInterface.getHelpSystem()) {
                return false;
            }

            if ('AccessDeniedException' === result.error) {
                ka.lastRequestBubble = ka.adminInterface.getHelpSystem().newBubble(
                    t('Access denied'),
                    t('You started a secured action or requested a secured information.') +
                        "<br/>" + 'URI: %s'.replace('%s', this.options.url) +
                        '<br/><a class="ka-Button" onclick="ka.open(\'admin/system/rest-logger\')">Details</a>',
                    15000
                );
                throw 'Access Denied %s'.replace('%s', this.options.url);
            } else {
                ka.lastRequestBubble = ka.adminInterface.getHelpSystem().newBubble(
                    t('Request error'),
                    t('There has been a error occured during the last request. It looks like the server has currently some troubles. Please try it again.') +
                        "<br/><br/>" + t('Error code: %s').replace('%s', result.error) +
                        "<br/>" + t('Error message: %s').replace('%s', result.message) +
                        "<br/>" + 'URI: %s'.replace('%s', this.options.url) +
                        '<br/><a class="ka-Button" onclick="ka.wm.open(\'admin/system/rest-logger\')">Details</a>',
                    15000
                );
                throw 'Request Error %s'.replace('%s', this.options.url);
            }
        }
    }
});