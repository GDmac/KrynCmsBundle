ka.ObjectAttributeProperty = new Class({

    Extends: ka.FieldProperty,

    _createLayout: function(){
        this.parent();

        this.tdTargetObject = new Element('td').inject(this.main, 'top');
        this.iTargetObject = new ka.Field({
            type: 'objectKey',
            options: {
                combobox: true
            },
            noWrapper: true
        }, this.tdTargetObject);
    },

    setValue: function (key, definition) {
        this.parent(key, definition);

        this.iTargetObject.setValue(definition ? definition.target : '');
    },

    getValue: function () {
        var value = this.parent();
        value.target = this.iTargetObject.getValue();

        return value;
    }

});