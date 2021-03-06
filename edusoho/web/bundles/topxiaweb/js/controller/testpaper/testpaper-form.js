define(function(require, exports, module) {
    function tanslate(value, tag)
    {
        return '<'+tag+'>'+value+'</'+tag+'>'
    }

    var Widget     = require('widget');
    var Notify = require('common/bootstrap-notify');
    var Validator = require('bootstrap.validator');
    require('common/validator-rules').inject(Validator);
    require('jquery.nouislider');
    require('jquery.nouislider-css');
    require('jquery.sortable');
    require('es-ckeditor');

    var TestpaperForm = Widget.extend({

        attrs: {
            validator: null
        },

        events: {
            'click [name=mode]': 'onClickModeField'
        },

        setup:function() {
            this.createValidator();
            if (this.get('haveBaseFields') === true) {
                this.initBaseFields();
            }
            if (this.get('haveBuildFields') === true) {
                this.initBuildFields();
            }
        },

        createValidator: function() {
            this.set('validator', new Validator({
                element: this.element
            }));
        },

        initBaseFields: function() {
            var validator = this.get('validator');

            // group: 'default'
            var editor = CKEDITOR.replace('testpaper-description-field', {
                toolbar: 'Simple',
                filebrowserImageUploadUrl: $('#testpaper-description-field').data('imageUploadUrl'),
                height: 100
            });

            validator.on('formValidate', function(elemetn, event) {
                editor.updateElement();
            });

            validator.addItem({
                element: '#testpaper-name-field',
                required: true
            });

            validator.addItem({
                element: '#testpaper-description-field',
                required: true,
                rule: 'maxlength{max:500}'
            });

            validator.addItem({
                element: '#testpaper-limitedTime-field',
                required: true,
                rule: 'integer,max{max:10000}'
            });
        },

        initBuildFields: function() {
            this.initDifficultyPercentageSlider();
            //@todo, refact it, wellming.
            this.initRangeField();
            this.initQuestionTypeSortable();

            var validator = this.get('validator'),
                self = this;

            validator.on('formValidated', function(error, msg, $form) {

                if (error) {
                    return ;
                }

                if(!self.checkBuildCountAndScoreInputs()){
                    return ;
                }

                if (!self.canBuild()) {
                    return ;
                }
                $('#testpaper-create-btn').button('submiting').addClass('disabled');

                $form[0].submit();
            });
        },

        onClickModeField: function(e) {
           if ($(e.currentTarget).val() == 'difficulty') {
                this.$('.difficulty-form-group').removeClass('hidden');
                this.$('.difficulty-percentage-slider').change();
            } else {
                this.$('.difficulty-form-group').addClass('hidden');
            }
        },

        canBuild: function() {
            var $form = $("#testpaper-form"),
                isOk = true;

            $.ajax($form.data('buildCheckUrl'), {
                type: 'POST',
                async: false,
                data: $form.serialize(),
                dataType: 'json',
                success: function(response) {
                    if (response.status != 'yes') {
                        var missingTexts = [];
                        var types = {
                            single_choice: Translator.trans('?????????'),
                            uncertain_choice: Translator.trans('??????????????????'),
                            choice: Translator.trans('?????????'),
                            fill: Translator.trans('?????????'),
                            determine: Translator.trans('?????????'),
                            essay: Translator.trans('?????????'),
                            material: Translator.trans('?????????')
                        }
                        $.each(response.missing, function(type, count) {
                            missingTexts.push(types[type] + Translator.trans('???%count%???',{count:tanslate(count, 'strong')}));
                          // missingTexts.push(types[type] + Translator.trans('???')+'<strong>' + count + '</strong>'+Translator.trans('???'));
                        });
                        Notify.danger(Translator.trans('??????????????????????????????????????????????????????')+'<br>' + missingTexts.join('???'), 5);
                        isOk = false;
                    }
                }
            });

            return isOk;
        },

        checkBuildCountAndScoreInputs: function() {
            var $form = this.element;
            var totalNumber = 0,
                isOk = true;

            $form.find('.item-number').each(function() {
                var number = $(this).val();
                if (!/^[0-9]*$/.test(number)) {
                    Notify.danger(Translator.trans('??????????????????????????????'));
                    $(this).focus();
                    isOk = false;
                    return false;
                }
                totalNumber += parseInt(number);
            });

            if (isOk) {
                if (totalNumber == 0) {
                    isOk = false;
                    Notify.danger(Translator.trans('??????????????????????????????0???'));
                    return isOk;
                }

                if (totalNumber > 1000) {
                    isOk = false;
                    Notify.danger(Translator.trans('??????????????????????????????1000??????'));
                    return isOk;
                }
            }

            $form.find('.item-score').each(function() {
                var score = $(this).val();

                if (score == '0') {
                    Notify.danger(Translator.trans('?????????????????????0???'));
                    isOk = false;
                    return false;
                }

                if (!/^(([1-9]{1}\d{0,2})|([0]{1}))(\.(\d){1})?$/.test(score)) {
                    Notify.danger(Translator.trans('??????????????????????????????????????????3????????????????????????????????????'));
                    $(this).focus();
                    isOk = false;
                    return false;
                }
                
            });

            $form.find('.item-miss-score').each(function() {
                var missScore = $(this).val();

                if (!/^(([1-9]{1}\d{0,2})|([0]{1}))(\.(\d){1})?$/.test(missScore)) {
                    Notify.danger(Translator.trans('????????????????????????????????????????????????3????????????????????????????????????'));
                    $(this).focus();
                    isOk = false;
                    return false;
                }

                var score=$(this).parent().find('.item-score').val();

                if (Number(missScore) > Number(score)) {
                    Notify.danger(Translator.trans('?????????????????????????????????????????????'));
                    isOk = false;
                    $(this).focus();
                    return false;
                }

            });


            return isOk;
        },

        initQuestionTypeSortable: function() {
            var $list = $('#testpaper-question-options').sortable({
                itemSelector: '.testpaper-question-option-item',
                handle: '.testpaper-question-option-item-sort-handler',
                serialize: function(parent, children, isContainer) {
                    return isContainer ? children : parent.attr('id');
                }
            });
        },

        initDifficultyPercentageSlider: function() {
            var self = this;
            return self.$('.difficulty-percentage-slider').noUiSlider({
                range: [0, 100],
                start: [30, 70],
                step: 5,
                serialization: {
                    resolution: 1
                },
                slide: function() {
                    this.trigger('change');
                }
            }).change(function() {
                var values = $(this).val();

                var simplePercentage = values[0],
                    normalPercentage = values[1] - values[0],
                    difficultyPercentage = 100 - values[1];

                self.$('.simple-percentage-text').html(Translator.trans('??????') + simplePercentage + '%');
                self.$('.normal-percentage-text').html(Translator.trans('??????') + normalPercentage + '%');
                self.$('.difficulty-percentage-text').html(Translator.trans('??????') + difficultyPercentage + '%');

                self.$('input[name="percentages[simple]"]').val(simplePercentage);
                self.$('input[name="percentages[normal]"]').val(normalPercentage);
                self.$('input[name="percentages[difficulty]"]').val(difficultyPercentage);

            });
        },

        getQuestionNums: function(){
            var rangeValue = $('input[name=range]:checked').val();
            var startLessonId = $("#testpaper-range-start").val();
            var endLessonId = $("#testpaper-range-end").val();

            var options = $("#testpaper-range-start").children();
            var status = false;
            var targets = "";
            $.each(options,function(i,n){
                var option = $(n);
                var value = option.attr("value");
                if(value == startLessonId){
                    status = true;
                    targets += value+",";
                    if(value == endLessonId){
                        status = false;
                    }
                } else if(value == endLessonId){
                    status = false;
                    targets += value+",";
                } else if(status){
                    targets += value+",";
                }
            });
            var courseId = $("#testpaper-form").data("courseId");
            $.get('../../../../../course/'+courseId+'/manage/testpaper/get_question_num', {range: rangeValue, targets:targets}, function(data){
                $('[role="questionNum"]').text(0);
                $.each(data,function(i,n){
                    $("[type='"+i+"']").text(n.questionNum);
                });
            });
        },

        initRangeField: function() {
            var self = this;
            $('input[name=range]').on('click', function() {
                if ($(this).val() == 'lesson') {
                    $("#testpaper-range-selects").show();
                } else {
                    $("#testpaper-range-selects").hide();
                }

                self._refreshRangesValue();
                self.getQuestionNums();
            });

            $("#testpaper-range-start").change(function() {
                var startIndex = self._getRangeStartIndex();

                self._resetRangeEndOptions(startIndex);

                self._refreshRangesValue();
                self.getQuestionNums();
            });

            $("#testpaper-range-end").change(function() {
                self._refreshRangesValue();
                self.getQuestionNums();
            });

        },

        _resetRangeEndOptions: function(startIndex) {
            if (startIndex > 0) {
                startIndex--;
                var $options = $("#testpaper-range-start option:gt(" + startIndex + ")");
            } else {
                var $options = $("#testpaper-range-start option");
            }

            var selected = $("#testpaper-range-end option:selected").val();

            $("#testpaper-range-end option").remove();
            $("#testpaper-range-end").html($options.clone());
            $("#testpaper-range-end option").each(function() {
                if ($(this).val() == selected) {
                    $("#testpaper-range-end").val(selected);
                }
            });
        },

        _refreshRangesValue: function() {
            var $ranges = $('input[name=ranges]');
            if ($('input[name=range]:checked').val() != 'lesson') {
                $ranges.val('');
                return;
            }

            var startIndex = this._getRangeStartIndex();
            var endIndex = this._getRangeEndIndex();

            if (startIndex < 0 || endIndex < 0) {
                $ranges.val('');
                return;
            }

            var values = [];
            for (var i = startIndex; i <= endIndex; i++) {
                values.push($("#testpaper-range-start option:eq(" + i + ")").val());
            }

            $ranges.val(values.join(','));
        },

        _getRangeStartIndex: function() {
            var $startOption = $("#testpaper-range-start option:selected");
            return parseInt($("#testpaper-range-start option").index($startOption));
        },

        _getRangeEndIndex: function() {
            var selected = $("#testpaper-range-end option:selected").val();
            if (selected == '') {
                return -1;
            }

            var index = -1;
            $("#testpaper-range-start option").each(function(i, item) {
                if ($(this).val() == selected) {
                    index = i;
                }
            });

            return index;
        }
    });

    exports.run = function() {
        new TestpaperForm({
            element: '#testpaper-form'
        });


    }

});