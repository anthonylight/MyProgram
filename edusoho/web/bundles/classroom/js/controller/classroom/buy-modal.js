define(function(require, exports, module) {

	var Validator = require('bootstrap.validator');
    require('common/validator-rules').inject(Validator);
    require("jquery.bootstrap-datetimepicker");
    var Notify = require('common/bootstrap-notify');
    var UserInfoFieldsItemValidate = require('../../../../topxiaweb/js/controller/user/userinfo-fields-common.js');

    var $from = $('#classroom-buy-form');
    var $modal = $from.parents('.modal');

    exports.run = function() {

        new UserInfoFieldsItemValidate({
            element: '#classroom-buy-form'
        });

        $('#show-coupon-input').on('click', function(){
            var $form = $(this).parents('form');
            if ($(this).data('status') == 'hide') {
                $form.find('.coupon-input-group').removeClass('hide');
                $form.find('#show-coupon').addClass('hide');
                $form.find('#hide-coupon').removeClass('hide');
                $(this).data('status', 'show');
            } else if ($(this).data('status') == 'show') {
                $form.find('.coupon-input-group').addClass('hide');
                $form.find('#show-coupon').removeClass('hide');
                $form.find('#hide-coupon').addClass('hide');
                $(this).data('status', 'hide');
            }
        });

        $("input[role='payTypeChoices']").on('click', function(){

            $("#password").prop("type","password");
            
            if($(this).val()=="chargeCoin") {
                $("#screct").show();

                validator.addItem({
                    element: '[name="password"]',
                    required: true,
                    rule: 'remote'
                });

                if (parseFloat($("#leftMoney").html()) <  parseFloat($("#neededMoney").html())){
                        $("#notify").show();
                        $modal.find('[type=submit]').addClass('disabled');
                 }
            }else if($(this).val()=="zhiFuBao"){
                validator.removeItem('[name="password"]');

                $("#screct").hide();
                $("#notify").hide();
                $modal.find('[type=submit]').removeClass('disabled');
            }
        })

        $('#join-course-btn').on('click', function(){
            $(this).button('loading');
        });

        $('.btn-use-coupon').on('click', function(){

            coupon_code = $('[name=coupon]').val();

            $.post($(this).data('url'), {code:coupon_code}, function(response){
                if (response.useable == 'yes') {

                    var html = '<span class="control-text"><strong class="money">'
                            + response.afterAmount
                            + '</strong><span class="text-muted">' + Translator.trans('???') + '</span> - <span class="text-muted">' + Translator.trans('?????????') + '</span><strong>'
                            + response.decreaseAmount
                            + '</strong><span class="text-muted">' + Translator.trans('???') + '</span></span>';

                    $('.money-text').html(html);
                    if (response.afterAmount === '0.00') {
                        $('#course-pay').text(Translator.trans('?????????'));
                    }

                    $('.coupon-error').html('');
                    $('[name=coupon]').attr("readonly",true);
                    $('.btn-use-coupon').addClass('disabled');
                } else {
                    var message = '<span class="text-danger">'+response.message+'</span>';
                    $('.coupon-error').html(message).show();
                    $('[name=coupon]').val('');
                }
            });
        });

    };

});