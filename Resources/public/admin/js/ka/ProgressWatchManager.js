/**
 *
 * @event done(ka.ProgressWatch progressWatch)
 * @event progress(ka.ProgressWatch progressWatch)
 * @event cancel(ka.ProgressWatch progressWatch)
 *
 * @event allDone(value, this)
 * @event allProgress(Number progress, this)
 *
 * @type {Class}
 */
ka.ProgressWatchManager = new Class({
    Extends: ka.ProgressWatch,

    progressWatch: [],
    allProgressDone: false,

    /**
     *
     * @param {Object} options
     * @param {*}      context
     */
    initialize: function(options, context) {
        this.parent(options, context);

        this.addEvent('done', this.updateDone.bind(this));
        this.addEvent('cancel', this.updateDone.bind(this));
        this.addEvent('error', this.updateDone.bind(this));
        this.addEvent('progress', this.updateProgress.bind(this));
    },

    updateProgress: function() {
        var progressValue = 0;
        var progressMax = 0;

        Array.each(this.progressWatch, function(progress) {
            progressMax += progress.getProgressRange();
            progressValue += progress.isFinished() ? progress.getProgressRange() : progress.getProgress();
        }.bind(this));

        progressValue = progressValue * 100 / progressMax;
        if (this.currentProgress !== progressValue) {
            this.allProgress(progressValue);
        }
    },

    updateDone: function() {
        this.updateProgress();

        var allDone = true;
        Array.each(this.progressWatch, function(progress) {
            if (!progress.isDone() && !progress.isCanceled() && !progress.isErrored()) {
                allDone = false;
            }
        }.bind(this));

        if (this.allProgressDone !== allDone) {
            this.allProgressDone = allDone;
            this.allDone();
        }
    },

    allProgress: function(progress) {
        this.currentProgress = progress;
        this.fireEvent('allProgress', [this.currentProgress, this]);
    },

    /**
     * Fires the 'allDone' event with the given value.
     * @param {*} value
     */
    allDone: function(value) {
        this.state = true;
        this.value = value;
        this.fireEvent('allDone', [this.value, this]);
    },

    /**
     * @param {ka.ProgressWatch} progressWatch
     */
    done: function(progressWatch) {
        this.fireEvent('done', progressWatch);
    },

    /**
     * @returns {Boolean}
     */
    isAllDone: function() {
        return this.allProgressDone;
    },

    /**
     * @param {ka.ProgressWatch} progressWatch
     */
    progress: function(progressWatch) {
        this.fireEvent('progress', progressWatch);
    },

    /**
     * Creates a new ka.ProgressWatch instance and attaches all
     * events to this manager.
     *
     * @param {Object} options
     * @param {*} context
     *
     * @returns {ka.ProgressWatch}
     */
    newProgressWatch: function(options, context) {
        var progress = new ka.ProgressWatch(options, context);

        progress.addEvent('done', function() {
            this.fireEvent('done', progress);
        }.bind(this));

        progress.addEvent('cancel', function() {
            this.fireEvent('cancel', progress);
        }.bind(this));

        progress.addEvent('progress', function() {
            this.fireEvent('progress', progress);
        }.bind(this));

        this.progressWatch.push(progress);
        return progress
    },

    /**
     * @param {ka.ProgressWatch} progressWatch
     */
    addProgressWatch: function(progressWatch) {
        this.progressWatch.push(progressWatch);
    },

    getAllProgressWatch: function() {
        return this.progressWatch;
    }
});