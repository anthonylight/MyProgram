define(function (require, exports, module) {
    var Validator = require('bootstrap.validator');
    require('common/validator-rules').inject(Validator);
    var Notify = require('common/bootstrap-notify');
    require("jquery.bootstrap-datetimepicker");
    require('jquery.sortable');
    require('es-ckeditor');
    exports.run = function () {

        var sortList = function ($list) {
            var data = $list.sortable("serialize").get();
            $.post($list.data('sortUrl'), {ids: data}, function (response) {
                var lessonNum = chapterNum = unitNum = 0;

                $list.find('.item-lesson, .item-chapter').each(function () {
                    var $item = $(this);
                    if ($item.hasClass('item-lesson')) {
                        lessonNum++;
                        $item.find('.number').text(lessonNum);
                    } else if ($item.hasClass('item-chapter-unit')) {
                        unitNum++;
                        $item.find('.number').text(unitNum);
                    } else if ($item.hasClass('item-chapter')) {
                        chapterNum++;
                        unitNum = 0;
                        $item.find('.number').text(chapterNum);
                    }

                });
            });
        };

        var $modal = $('#live-lesson-form').parents('.modal');
        var $content = $("#live-lesson-content-field");
        var now = new Date();
        var validator = new Validator({

            element: '#live-lesson-form',
            autoSubmit: false,
            onFormValidated: function (error, results, $form) {
                if (error) {
                    return false;
                }
                $('#live-course-create-btn').button('submiting').addClass('disabled');
                var $panel = $('.lesson-manage-panel');
                $.post($form.attr('action'), $form.serialize(), function (html) {
                    var id = '#' + $(html).attr('id'),
                        $item = $(id);
                    var $parent = $('#' + $form.data('parentid'));
                    if ($item.length) {
                        $item.replaceWith(html);
                        Notify.success(Translator.trans('???????????????'));
                    } else {
                        $panel.find('.empty').remove();
                        if ($parent.length) {
                            var add = 0;
                            if ($parent.hasClass('item-chapter  clearfix')) {
                                $parent.nextAll().each(function () {
                                    if ($(this).hasClass('item-chapter  clearfix')) {
                                        $(this).before(html);
                                        add = 1;
                                        return false;
                                    }
                                });
                                if (add != 1) {
                                    $("#course-item-list").append(html);
                                    add = 1;
                                }

                            } else {
                                $parent.nextAll().each(function () {
                                    if ($(this).hasClass('item-chapter  clearfix'))
                                        return false;
                                    if ($(this).hasClass('item-chapter item-chapter-unit clearfix')) {
                                        $(this).before(html);
                                        add = 1;
                                        return false;
                                    }
                                });
                            }
                            if (add != 1)
                                $parent.after(html);
                            var $list = $("#course-item-list");
                            sortList($list);
                        } else {
                            $("#course-item-list").append(html);
                        }
                        Notify.success(Translator.trans('????????????????????????'));
                    }
                    $modal.modal('hide');
                }).error(function (event) {
                    var error = event.responseText;
                    if (error && error.length > 0) {
                        error = JSON.parse(error);
                        Notify.danger(error.error.message);
                    } else {
                        Notify.danger(Translator.trans('????????????'));
                    }
                });
            }
        });

        Validator.addRule('remote_check',
            function (options, commit) {

                var element = $('#live_lesson_time_check');
                var startTime = $('[name=startTime]').val();
                var length = $('[name=timeLength]').val();

                if (startTime && length) {
                    url = element.data('url');
                    $.get(url, {startTime: startTime, length: length}, function (response) {
                        commit(response.success, response.message);
                    }, 'json');
                } else {
                    return true;
                }
            }
        );

        Validator.addRule('live_date_check',
            function () {

                var thisTime = $('[name=startTime]').val();
                thisTime = thisTime.replace(/-/g, "/");
                thisTime = Date.parse(thisTime) / 1000;
                var nowTime = Date.parse(new Date()) / 1000;

                if (nowTime <= thisTime) {
                    return true;
                } else {
                    return false;
                }
            },Translator.trans('????????????????????????????????????')

        );

        var thisTime = $('[name=startTime]').val();
        thisTime = thisTime.replace(/-/g, "/");
        thisTime = Date.parse(thisTime) / 1000;
        var nowTime = Date.parse(new Date()) / 1000;

        if (nowTime > thisTime) {
            $('[name=startTime]').attr('disabled', true);
            $('#live-length-field').attr('disabled', true);
            $('#starttime-help-block').html(Translator.trans('??????????????????????????????,????????????'));
            $('#starttime-help-block').css('color', '#a94442');
            $('#timelength-help-block').html(Translator.trans('??????????????????????????????,????????????'));
            $('#timelength-help-block').css('color', '#a94442');
        } else {
            $('[name=startTime]').attr('disabled', false);
        }

        Validator.addRule('live_lesson_title', /^[^(<|>|'|"|&|???|???|???|???)]*$/, "????????????<???>???\"???&??????????????????????????????");
        validator.addItem({
            element: '#live-title-field',
            rule: 'live_lesson_title',
            required: true
        });

        validator.addItem({
            element: '[name=startTime]',
            required: true,
            rule: 'live_date_check',
            errormessageRequired: Translator.trans('??????????????????????????????')
        });

        validator.addItem({
            element: '[name=timeLength]',
            required: true,
            rule: 'positive_integer remote_check',
            display: Translator.trans('????????????'),
            onItemValidated: function (error, message, elem) {
                if (error) {
                    return;
                }

                var params = {startTime: $('[name=startTime]').val(), length: $('[name=timeLength]').val()};

                if (!params.startTime) {
                    return;
                }

                $.get($(elem).data('calculateLeftCapacityUrl'), params, function (response) {
                    var maxStudentNum = parseInt($(elem).data('maxStudentNum'));
                    var leftCapacity = parseInt(response);
                    if (maxStudentNum > leftCapacity) {
                        var message = Translator.trans('???????????????????????????????????????????????????') + '<strong>' + (maxStudentNum - leftCapacity) + '</strong> ' + Translator.trans('????????????????????????????????????????????????????????????????????????');
                        $(elem).parent().find('.help-block').html('<div class="alert alert-warning">' + message + '</div>');
                    }
                }, 'json');

            }
        });

        $("[name=startTime]").datetimepicker({
            autoclose: true
        }).on('hide', function (ev) {
            validator.query('[name=startTime]').execute();
        });
        $('[name=startTime]').datetimepicker('setStartDate', now);

        // course
        var editor = CKEDITOR.replace('live_lesson-content-field', {
            toolbar: 'Simple',
            filebrowserImageUploadUrl: $('#live_lesson-content-field').data('imageUploadUrl'),
            height: 300
        });


        validator.on('formValidate', function (elemetn, event) {
            editor.updateElement();
            var z = editor.getData();
            var x = editor.getData().match(/<embed[\s\S]*?\/>/g);
            if (x) {
                for (var i = x.length - 1; i >= 0; i--) {
                    var y = x[i].replace(/\/>/g, "wmode='Opaque' \/>");
                    var z = z.replace(x[i], y);
                }
                ;
            }
            $content.val(z);
        });
    };

});