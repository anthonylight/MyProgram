define(function(require, exports, module) {

    var Widget     = require('widget');
    var Validator = require('bootstrap.validator');
    require('common/validator-rules').inject(Validator);
    require("jquery.bootstrap-datetimepicker");

    var UserInfoFieldsItemValidate = Widget.extend({

        attrs: {
            validator: null
        },

        events: {

        },

        setup:function() {
            this.createValidator();
            this.initBaseFields();
            this.initComponents();
        },

        initComponents: function () {
            $('.date').each(function () {
                $(this).datetimepicker({
                    autoclose: true,
                    format: 'yyyy-mm-dd',
                    minView: 2
                });
            });
        },

        createValidator: function() {
            var validator = new Validator({
                element: this.element,
                failSilently: true,
                onFormValidated: function(error, results, $form) {
                    if (error) {
                        return false;
                    }
                    this.element.find('[type=submit]').button('submiting').addClass('disabled');
                }
            });

            this.set('validator', validator);
        },

        initBaseFields: function(){
            var validator = this.get('validator');

            validator.addItem({
                element: '[name="email"]',
                required: true,
                rule: 'email remote'
            });

            validator.addItem({
                element: '[name="mobile"]',
                required: true,
                rule: 'phone'
            });

            validator.addItem({
                element: '[name="truename"]',
                required: true,
                rule: 'chinese_alphanumeric byte_minlength{min:4} byte_maxlength{max:36} '
            });

            validator.addItem({
                element: '[name="qq"]',
                required: true,
                rule: 'qq'
            });

            validator.addItem({
                element: '[name="idcard"]',
                required: true,
                rule: 'idcard'
            });

            validator.addItem({
                element: '[name="gender"]',
                required: true,
                errormessageRequired: Translator.trans('???????????????')
            });

            validator.addItem({
                element: '[name="company"]',
                required: true
            });

            validator.addItem({
                element: '[name="job"]',
                required: true
            });

            validator.addItem({
                element: '[name="weibo"]',
                required: true,
                rule: 'url',
                errormessageUrl: Translator.trans('??????????????????????????????http://??????https://?????????')
            });

            validator.addItem({
                element: '[name="weixin"]',
                required: true
            });
            for(var i=1;i<=5;i++){
                 validator.addItem({
                     element: '[name="intField'+i+'"]',
                     required: true,
                     rule: 'int'
                 });

                  validator.addItem({
                    element: '[name="floatField'+i+'"]',
                    required: true,
                    rule: 'float'
                 });

                 validator.addItem({
                    element: '[name="dateField'+i+'"]',
                    required: true,
                    rule: 'date'
                 });
            }

            for(var i=1;i<=10;i++){
                validator.addItem({
                    element: '[name="varcharField'+i+'"]',
                    required: true
                });

                validator.addItem({
                    element: '[name="textField'+i+'"]',
                    required: true
                });

            }
        }
        
    });

    module.exports = UserInfoFieldsItemValidate;
});