define(function(require, exports, module) {
    var Widget = require('widget'),
        Class = require('class'),
        Store = require('store'),
        Backbone = require('backbone'),
        swfobject = require('swfobject'),
        Scrollbar = require('jquery.perfect-scrollbar'),
        Notify = require('common/bootstrap-notify');
        chapterAnimate = require('../course/widget/chapter-animate');
        var Messenger = require('../player/messenger');

        require('mediaelementplayer');

    var Toolbar = require('../lesson/lesson-toolbar');

    var SlidePlayer = require('../widget/slider-player');
    var DocumentPlayer = require('../widget/document-player');

    var iID = null;

    var LessonDashboard = Widget.extend({

        _router: null,

        _toolbar: null,

        _lessons: [],

        _counter: null,

        events: {
            'click [data-role=next-lesson]': 'onNextLesson',
            'click [data-role=prev-lesson]': 'onPrevLesson',
            'click [data-role=finish-lesson]': 'onFinishLesson',
            'click [data-role=ask-question]': 'onAskQuestion'
        },

        attrs: {
            courseId: null,
            courseUri: null,
            dashboardUri: null,
            lessonId: null,
            type: null,
            watchLimit: false,
            starttime: null
        },

        setup: function() {
            this._readAttrsFromData();
            this._initToolbar();
            this._initRouter();
            this._initListeners();
            this._initChapter();

            $('.prev-lesson-btn, .next-lesson-btn').tooltip();
        },

        onNextLesson: function(e) {
            var next = this._getNextLessonId();
            if (next > 0) {
                this._router.navigate('lesson/' + next, {trigger: true});
            }
        },

        onPrevLesson: function(e) {
            var prev = this._getPrevLessonId();
            if (prev > 0) {
                this._router.navigate('lesson/' + prev, {trigger: true});
            }
        },

        onFinishLesson: function(e) {
            var $btn = this.element.find('[data-role=finish-lesson]');
            if (!$btn.hasClass('btn-success')) {
                this._onFinishLearnLesson();
            }
        },

        _startLesson: function() {
            var toolbar = this._toolbar,
                self = this;
            var url = '../../course/' + this.get('courseId') + '/lesson/' + this.get('lessonId') + '/learn/start';
            $.post(url, function(result) {
                if (result == true) {
                    toolbar.trigger('learnStatusChange', {lessonId:self.get('lessonId'), status: 'learning'});
                }
            }, 'json');
        },

        _onFinishLearnLesson: function() {
            var $btn = this.element.find('[data-role=finish-lesson]'),
            toolbar = this._toolbar,
            self = this;

            var url = '../../course/' + this.get('courseId') + '/lesson/' + this.get('lessonId') + '/learn/finish';
            $.post(url, function(response) {

                if (response.isLearned) {
                    $('#course-learned-modal').modal('show');
                }

                if (!response.canFinish && response.html) {
                    $("#modal").html(response.html);
                    $("#modal").modal('show');
                    return false;
                } else if (response.canFinish && response.html != '') {
                    $("#modal").html(response.html);
                    $("#modal").modal('show');
                }

                $btn.addClass('btn-success');
                $btn.attr('disabled', true);
                $btn.find('.glyphicon').removeClass('glyphicon-unchecked').addClass('glyphicon-check');
                toolbar.trigger('learnStatusChange', {lessonId:self.get('lessonId'), status: 'finished'});

            }, 'json');

        },

        _onCancelLearnLesson: function() {
            var $btn = this.element.find('[data-role=finish-lesson]'),
                toolbar = this._toolbar,
                self = this;
            var url = '../../course/' + this.get('courseId') + '/lesson/' + this.get('lessonId') + '/learn/cancel';
            $.post(url, function(json) {
                $btn.removeClass('btn-success');
                $btn.find('.glyphicon').removeClass('glyphicon-check').addClass('glyphicon-unchecked');
                toolbar.trigger('learnStatusChange', {lessonId:self.get('lessonId'), status: 'learning'});
            }, 'json');
        },

        _readAttrsFromData: function() {
            this.set('courseId', this.element.data('courseId'));
            this.set('courseUri', this.element.data('courseUri'));
            this.set('dashboardUri', this.element.data('dashboardUri'));
            this.set('watchLimit', this.element.data('watchLimit'));
            this.set('starttime', this.element.data('starttime'));
        },

        _initToolbar: function() {
            this._toolbar = new Toolbar({
                element: '#lesson-dashboard-toolbar',
                activePlugins:  app.arguments.plugins,
                courseId: this.get('courseId')
            }).render();

            $('#lesson-toolbar-primary li[data-plugin=lesson]').trigger('click');
        },

        _initRouter: function() {
            var that = this,
                DashboardRouter = Backbone.Router.extend({
                routes: {
                    "lesson/:id": "lessonShow"
                },

                lessonShow: function(id) {
                    that.set('lessonId', id);
                }
            });

            this._router = new DashboardRouter();
            Backbone.history.start({pushState: false, root:this.get('dashboardUri')} );
        },

        _initListeners: function() {
            var that = this;
            this._toolbar.on('lessons_ready', function(lessons){
                that._lessons = lessons;
                that._showOrHideNavBtn();
                
                if ($('.es-wrap [data-toggle="tooltip"]').length > 0) {
                    $('.es-wrap [data-toggle="tooltip"]').tooltip({container: 'body'});
                }
            });
        },

        _afterLoadLesson: function(lessonId) {
            if (this._counter && this._counter.timerId) {
                clearInterval(this._counter.timerId);
            }

            var self = this;
            this._counter = new Counter(self, this.get('courseId'), lessonId, this.get('watchLimit'));
            this._counter.setTimerId(setInterval(function(){self._counter.execute()}, 1000));
        },

        _onChangeLessonId: function(id) {
            var self = this;
            if (!this._toolbar) {
                return ;
            }
            this._toolbar.set('lessonId', id);

            swfobject.removeSWF('lesson-swf-player');

            $('#lesson-iframe-content').empty();
            $('#lesson-video-content').html("");

            this.element.find('[data-role=lesson-content]').hide();
            var that = this;
            var _readCourseTitle = function (lesson, name) { // chapter unit
                var data={};
                if(app.arguments.customChapter == 1){
                    data.number = that.element.find('[data-role=' + name + '-number]');
                    data.title =  name == 'chapter' ? lesson.chapterNumber : lesson.unitNumber;
                }else{
                    data.number = that.element.find('[data-role=custom-' + name + '-number]');
                    data.title  =  name == 'chapter' ? (lesson.chapter == null ? "" :lesson.chapter.title) : (lesson.unit == null ? "":lesson.unit.title);
                }
                return data;
            }
            $.get(this.get('courseUri') + '/lesson/' + id, function(lesson) {
                
                that.set('type',lesson.type);
                that.element.find('[data-role=lesson-title]').html(lesson.title);
                $(".watermarkEmbedded").html('<input type="hidden" id="videoWatermarkEmbedded" value="'+lesson.videoWatermarkEmbedded+'" />');
                var $titleStr = "";
                $titleArray = document.title.split(' - ');
                $.each($titleArray,function(key,val){
                    $titleStr += val + ' - ';
                })
                document.title = lesson.title + ' - ' + $titleStr.substr(0,$titleStr.length-3);
                if(app.arguments.customChapter == 1){
                    that.element.find('[data-role=lesson-number]').html(lesson.number);
                }
               
                if (parseInt(lesson.chapterNumber) > 0) {
                    var data= _readCourseTitle(lesson, 'chapter');
                    data.number.html(data.title).parent().show().next().show();
                } else {
                  var data= _readCourseTitle(lesson, 'chapter');
                  data.number.parent().hide().next().hide();
                }

                if (parseInt(lesson.unitNumber) > 0) {
                    var data= _readCourseTitle(lesson, 'unit');
                    data.number.html(data.title).parent().show().next().show();
                } else {
                    var data= _readCourseTitle(lesson, 'unit');
                    data.number.parent().hide().next().hide();
                }

                if ( (lesson.status != 'published') && !/preview=1/.test(window.location.href)) {
                    $("#lesson-unpublished-content").show();
                    return;
                }

                var number = lesson.number -1;

                if (lesson.canLearn.status != 'yes') {
                    $("#lesson-alert-content .lesson-content-text-body").html(lesson.canLearn.message);
                    $("#lesson-alert-content").show();
                    return;
                }

                if (lesson.mediaError) {
                    Notify.danger(lesson.mediaError);
                    return ;
                }

                if (lesson.mediaSource == 'iframe') {
                    var html = '<iframe src="' + lesson.mediaUri + '" style="position:absolute; left:0; top:0; height:100%; width:100%; border:0px;" scrolling="no"></iframe>';

                    $("#lesson-iframe-content").html(html);
                    $("#lesson-iframe-content").show();

                } else if (lesson.type == 'video' || lesson.type == 'audio') {
                    if(lesson.mediaSource == 'self') {
                        var lessonVideoDiv = $('#lesson-video-content');

                        if ((lesson.mediaConvertStatus == 'waiting') || (lesson.mediaConvertStatus == 'doing')) {
                            Notify.warning('?????????????????????????????????????????????????????????');
                            return ;
                        }

                        var playerUrl = '../../course/' + lesson.courseId + '/lesson/' + lesson.id + '/player';
                        if(self.get('starttime')){
                            playerUrl += "?starttime=" + self.get('starttime');
                        }
                        var html = '<iframe src=\''+playerUrl+'\' name=\'viewerIframe\' id=\'viewerIframe\' width=\'100%\'allowfullscreen webkitallowfullscreen height=\'100%\' style=\'border:0px\'></iframe>';

                        $("#lesson-video-content").show();
                        $("#lesson-video-content").html(html);

                        var messenger = new Messenger({
                            name: 'parent',
                            project: 'PlayerProject',
                            children: [ document.getElementById('viewerIframe') ],
                            type: 'parent'
                        });

                        messenger.on("ready", function(){
                        });

                        messenger.on("ended", function(){
                            var player = that.get("player");
                            player.playing = false;
                            that.set("player", player);
                            that._onFinishLearnLesson();
                        });

                        messenger.on("playing", function(){
                            var player = that.get("player");
                            player.playing = true;
                            that.set("player", player);
                        });

                        messenger.on("paused", function(){
                            var player = that.get("player");
                            player.playing = false;
                            that.set("player", player);
                        });
                        that.set("player", {});

                    } else {
                        $("#lesson-swf-content").html('<div id="lesson-swf-player"></div>');
                        swfobject.embedSWF(lesson.mediaUri, 
                            'lesson-swf-player', '100%', '100%', "9.0.0", null, null, 
                            {wmode:'opaque',allowFullScreen:'true'});
                        $("#lesson-swf-content").show();
                    }
                } else if (lesson.type == 'text' ) {
                    $("#lesson-text-content").find('.lesson-content-text-body').html(lesson.content);
                    $("#lesson-text-content").show();
                    $("#lesson-text-content").perfectScrollbar({wheelSpeed:50});
                    $("#lesson-text-content").scrollTop(0);
                    $("#lesson-text-content").perfectScrollbar('update');

                } else if (lesson.type =="live") {
                    var liveStartTimeFormat = lesson.startTimeFormat;
                    var liveEndTimeFormat = lesson.endTimeFormat;
                    var startTime = lesson.startTime;
                    var endTime = lesson.endTime;

                    var courseId = lesson.courseId;
                    var lessonId = lesson.id;
                    var $liveNotice = "<p>???????????? <strong>"+liveStartTimeFormat+"</strong> ???????????? <strong>"+liveEndTimeFormat+"</strong> ?????????????????????10????????????????????????</p>";
                    // if(iID) {
                    //     clearInterval(iID);
                    // }

                    var intervalSecond = 0;

                    function generateHtml() {
                        var nowDate = lesson.nowDate + intervalSecond;
                        var startLeftSeconds = parseInt(startTime - nowDate);
                        var endLeftSeconds = parseInt(endTime - nowDate);
                        var days = Math.floor(startLeftSeconds / (60 * 60 * 24));
                        var modulo = startLeftSeconds % (60 * 60 * 24);
                        var hours = Math.floor(modulo / (60 * 60));
                        modulo = modulo % (60 * 60);
                        var minutes = Math.floor(modulo / 60);
                        var seconds = modulo % 60;
                        var $replayGuid = "????????????";
                        $replayGuid += "<br>";

                        $replayGuid += "&nbsp;&nbsp;&nbsp;&nbsp;"+'??????????????????????????????????????????';
                        $replayGuid += "<span style='color:red'>"+'????????????'+"</span>";
                        $replayGuid += '??????????????????????????????';
                        $replayGuid += "<span style='color:red'>????????????</span>";
                        $replayGuid += '?????????';
                        $replayGuid += "<span style='color:red'>????????????</span>";
                        $replayGuid += '???????????????????????????????????????????????????';
                        $replayGuid += "<br>";

                        $countDown =  that._getCountDown(days,hours,minutes,seconds);


                        if (0 < startLeftSeconds && startLeftSeconds < 7200) {
                            $liveNotice = "<p>???????????? <strong>" + liveStartTimeFormat + "</strong> ???????????? <strong>" + liveEndTimeFormat + "</strong> ??????????????????????????????????????????</p>";
                            var url = self.get('courseUri') + '/lesson/' + id + '/live_entry';
                            if (lesson.isTeacher) {
                                $countDown = $replayGuid;
                                $countDown += "<p>??????" + hours + "??????" + minutes + "??????" + seconds + "???&nbsp;";
                            } else {
                                $countDown = "<p>??????" + hours + "??????" + minutes + "??????" + seconds + "???&nbsp;";
                            }
                        };

                        if (startLeftSeconds <= 0 && endLeftSeconds>0) {
                            // clearInterval(iID);
                            $("#lesson-swf-content").show();
                            var player = new prismplayer({
                                id: "lesson-swf-content", // ??????id
                                source: lesson.liveUrl,
                                autoplay: true,      // ????????????
                                width: "100%",       // ???????????????
                                height: "86%"      // ???????????????
                            });
                            // $("#lesson-live-content").hide();
                            return true;
                        };

                        if (endLeftSeconds <= 0) {
                            $liveNotice = "<p>??????????????????</p>";
                            $countDown='';
                            if(typeof lesson.ReplayUrl !== 'undefined'){
                                // clearInterval(iID);
                                $("#lesson-swf-content").show();
                                var player = new prismplayer({
                                    id: "lesson-swf-content", // ??????id
                                    source: lesson.ReplayUrl,
                                    autoplay: true,      // ????????????
                                    width: "100%",       // ???????????????
                                    height: "86%"      // ???????????????
                                });
                                // $("#lesson-live-content").hide();
                                return true;
                            }





                            // if(lesson.replays && lesson.replays.length>0){
                            //     $.each(lesson.replays, function(i,n){
                            //         $countDown += "<a class='btn btn-primary' href='"+n.url+"' target='_blank'>"+n.title+"</a>&nbsp;&nbsp;";
                            //     });
                            // }
                        };

                        // $("#lesson-live-content").find('.lesson-content-text-body').html($liveNotice + '<div style="padding-bottom:15px; border-bottom:1px dashed #ccc;">' + lesson.summary + '</div>' + '<br>' + $countDown);

                        intervalSecond++;
                    }

                    generateHtml();
                    // iID = setInterval(generateHtml, 1000);

                    // $("#lesson-live-content").show();
                    // $("#lesson-live-content").perfectScrollbar({wheelSpeed:50});
                    // $("#lesson-live-content").scrollTop(0);
                    // $("#lesson-live-content").perfectScrollbar('update');

                } else if (lesson.type == 'testpaper') {
                    var url = '../../lesson/' + id + '/test/' + lesson.mediaId + '/do';
                    var html = '<span class="text-info">?????????????????????????????????????????????????????????????????????<a href="' + url + '" class="btn btn-primary btn-sm" target="_blank">????????????</a></span>';
                    var html = '<span class="text-info">????????????????????????...</span>';
                    $("#lesson-testpaper-content").find('.lesson-content-text-body').html(html);
                    $("#lesson-testpaper-content").show();

                    //?????????????????????
                    if(lesson.testMode == 'realTime'){
                        var testStartTimeFormat = lesson.testStartTimeFormat;
                        var testEndTimeFormat = lesson.testEndTimeFormat;
                        var testStartTime = lesson.testStartTime;
                        var testEndTime = lesson.testEndTime;
                        var limitedTime = lesson.limitedTime;

                        var courseId = lesson.courseId;
                        var lessonId = lesson.id;
                        var $testNotice = "<p>?????????????????? <strong>"+testStartTimeFormat+"</strong> ???????????????<strong>"+testEndTimeFormat+"</strong> ?????????????????????10????????????????????????</p>";
                        if(iID) {
                            clearInterval(iID);
                        }

                        var intervalSecond = 0;

                        function generateTestHtml() {
                            var nowDate = lesson.nowDate + intervalSecond;
                            var testStartLeftSeconds = parseInt(testStartTime - nowDate);
                            var testEndLeftSeconds = parseInt(testEndTime - nowDate);
                            var testStartRightSeconds = parseInt(nowDate - testStartTime);
                            var days = Math.floor(testStartLeftSeconds / (60 * 60 * 24));
                            var modulo = testStartLeftSeconds % (60 * 60 * 24);
                            var hours = Math.floor(modulo / (60 * 60));
                            modulo = modulo % (60 * 60);
                            var minutes = Math.floor(modulo / 60);
                            var seconds = modulo % 60;
                            var limitedHouse = Math.floor(testEndLeftSeconds / (60 * 60) );
                            var limitedMinutes  = Math.floor(testEndLeftSeconds % (60 * 60) / 60 );
                            var limitedSeconds = (testEndLeftSeconds % 60)

                            if (0 < testStartLeftSeconds ) {
                                $testNotice = '<p class="text-center mtl mbl"><i class="text-primary mrm es-icon es-icon-info"></i>?????????????????????????????????????????????<span class="gray-darker plm">'+days+'???</span></p><p class="text-center text-primary mbl"><span style="display:inline-block;width:80px;height:80px;line-height:80px;background:#46c37b;color:#fff;font-size:48px;border-radius:4px;margin:0 10px;">'+days+'</span>???<span style="display:inline-block;width:80px;height:80px;line-height:80px;background:#46c37b;color:#fff;font-size:48px;border-radius:4px;margin:0 10px;">'+hours+'</span>???<span style="display:inline-block;width:80px;height:80px;line-height:80px;background:#46c37b;color:#fff;font-size:48px;border-radius:4px;margin:0 10px;">'+minutes+'</span>???<span style="display:inline-block;width:80px;height:80px;line-height:80px;background:#46c37b;color:#fff;font-size:48px;border-radius:4px;margin:0 10px;">'+seconds+'</span>???</p>   <p class="text-center color-gray">???????????????????????????<a class="mlm mrm btn btn-sm btn-default" disabled>????????????</a>??????</p>';
                            };

                            if (0 < testStartRightSeconds) {
                                $testNotice = '<p class="text-center mtm mbm"><i class="color-warning mrm es-icon es-icon-info" ></i>??????????????????</p><p class="text-center text-primary mbl"><span style="display:inline-block;width:80px;height:80px;line-height:80px;background:#ffcb4b;color:#fff;font-size:48px;border-radius:4px;margin:0 10px;">0</span>???<span style="display:inline-block;width:80px;height:80px;line-height:80px;background:#ffcb4b;color:#fff;font-size:48px;border-radius:4px;margin:0 10px;">' + limitedHouse + '</span>???<span style="display:inline-block;width:80px;height:80px;line-height:80px;background:#ffcb4b;color:#fff;font-size:48px;border-radius:4px;margin:0 10px;">' + limitedMinutes + '</span>???<span style="display:inline-block;width:80px;height:80px;line-height:80px;background:#ffcb4b;color:#fff;font-size:48px;border-radius:4px;margin:0 10px;">' + limitedSeconds + '</span>???</p>   <p class="text-center color-gray">?????????<a href="' + url + '" class="mlm mrm btn btn-sm  btn-primary" >????????????</a>??????</p>';
                            };

                            if (testEndLeftSeconds <= 0) {
                                clearInterval(iID);
                                $testNotice = '<p class="text-center mtl mbl color-gray"><i class="color-gray mrm es-icon es-icon-info"></i>??????????????????</p><p class="text-center color-gray mbl"><span style="display:inline-block;width:80px;height:80px;line-height:80px;background:#e6e6e6;color:#fff;font-size:48px;border-radius:4px;margin:0 10px;">00</span>???<span style="display:inline-block;width:80px;height:80px;line-height:80px;background:#e6e6e6;color:#fff;font-size:48px;border-radius:4px;margin:0 10px;">00</span>???<span style="display:inline-block;width:80px;height:80px;line-height:80px;background:#e6e6e6;color:#fff;font-size:48px;border-radius:4px;margin:0 10px;">00</span>???<span style="display:inline-block;width:80px;height:80px;line-height:80px;background:#e6e6e6;color:#fff;font-size:48px;border-radius:4px;margin:0 10px;">00</span>???</p>';

                            };

                            $("#lesson-testpaper-content").find('.lesson-content-text-body').html($testNotice);

                            intervalSecond++;
                        }

                        generateTestHtml();

                        iID = setInterval(generateTestHtml, 1000);

                        $("#lesson-testpaper-content").show();
                        $("#lesson-testpaper-content").perfectScrollbar({wheelSpeed:50});
                        $("#lesson-testpaper-content").scrollTop(0);
                        $("#lesson-testpaper-content").perfectScrollbar('update');

                    }else{
                        $.get('../../testpaper/' + lesson.mediaId + '/user_result/json', function(result) {
                            if (result.error) {
                                html = '<span class="text-danger">' + result.error + '</span>';
                            } else {
                                if (result.status == 'nodo') {
                                    html = '?????????????????????????????????????????????????????????<a href="' + url + '" class="btn btn-primary btn-sm" target="_blank">????????????</a>';                               
                                } else if (result.status == 'finished') {
                                    var redoUrl = '../../lesson/' + id + '/test/' + lesson.mediaId + '/redo';
                                    var resultUrl = '../../test/' + result.resultId + '/result?targetType=lesson&targetId=' + id;
                                    html = '??????????????????' + '<a href="' + redoUrl + '" class="btn btn-default btn-sm" target="_blank">????????????</a>' + '<a href="' + resultUrl + '" class="btn btn-link btn-sm" target="_blank">????????????</a>';
                                } else if (result.status == 'doing' || result.status == 'paused') {
                                    html = '????????????????????????<a href="' + url + '" class="btn btn-primary btn-sm" target="_blank">????????????</a>';
                                } else if (result.status == 'reviewing') {
                                    html = '?????????????????????<a href="' + url + '" class="btn btn-primary btn-sm" target="_blank">????????????</a>'
                                }
                            }

                            $("#lesson-testpaper-content").find('.lesson-content-text-body').html(html);

                        }, 'json');

                    }


                } else if (lesson.type == 'ppt') {
                    $.get(that.get('courseUri') + '/lesson/' + id + '/ppt', function(response) {
                        if (response.error) {
                            var html = '<div class="lesson-content-text-body text-danger">' + response.error.message + '</div>';
                            $("#lesson-ppt-content").html(html).show();
                            return ;
                        }

                        var html = '<div class="slide-player"><div class="slide-player-body loading-background"></div><div class="slide-notice"><div class="header">?????????????????????????????????<button type="button" class="close">??</button></div></div><div class="slide-player-control clearfix"><a href="javascript:" class="goto-first"><span class="glyphicon glyphicon-step-backward"></span></a><a href="javascript:" class="goto-prev"><span class="glyphicon glyphicon-chevron-left"></span></a><a href="javascript:" class="goto-next"><span class="glyphicon glyphicon-chevron-right"></span></span></a><a href="javascript:" class="goto-last"><span class="glyphicon glyphicon-step-forward"></span></a><a href="javascript:" class="fullscreen"><span class="glyphicon glyphicon-fullscreen"></span></a><div class="goto-index-input"><input type="text" class="goto-index form-control input-sm" value="1">&nbsp;/&nbsp;<span class="total"></span></div></div></div>';
                        $("#lesson-ppt-content").html(html).show();

                        var watermarkUrl = $("#lesson-ppt-content").data('watermarkUrl');
                        if (watermarkUrl) {
                            $.get(watermarkUrl, function(watermark) {
                                var player = new SlidePlayer({
                                    element: '.slide-player',
                                    slides: response,
                                    watermark: watermark
                                });
                            });

                        } else {
                            var player = new SlidePlayer({
                                element: '.slide-player',
                                slides: response
                            });
                        }


                    }, 'json');
                } else if (lesson.type == 'document' ) {

                    $.get(that.get('courseUri') + '/lesson/' + id + '/document', function(response) {
                        if (response.error) {
                            var html = '<div class="lesson-content-text-body text-danger">' + response.error.message + '</div>';
                            $("#lesson-document-content").html(html).show();
                            return ;
                        }

                        var html = '<iframe id=\'viewerIframe\' width=\'100%\'allowfullscreen webkitallowfullscreen height=\'100%\'></iframe>';
                        $("#lesson-document-content").html(html).show();

                        var watermarkUrl = $("#lesson-document-content").data('watermarkUrl');
                        if (watermarkUrl) {
                            $.get(watermarkUrl, function(watermark) {
                                var player = new DocumentPlayer({
                                    element: '#lesson-document-content',
                                    swfFileUrl:response.swfUri,
                                    pdfFileUrl:response.pdfUri,
                                    watermark: {
                                        'xPosition': 'center',
                                        'yPosition': 'center',
                                        'rotate': 45,
                                        'contents': watermark
                                    }
                                });
                            });
                        } else {
                            var player = new DocumentPlayer({
                                element: '#lesson-document-content',
                                swfFileUrl:response.swfUri,
                                pdfFileUrl:response.pdfUri
                            });
                        }
                    }, 'json');
                } else if (lesson.type == 'flash' ) {
                    
                    if (!swfobject.hasFlashPlayerVersion('11')) {
                        var html = '<div class="alert alert-warning alert-dismissible fade in" role="alert">';
                        html += '<button type="button" class="close" data-dismiss="alert" aria-label="Close">';
                        html += '<span aria-hidden="true">??</span>';
                        html += '</button>';
                        html += '?????????????????????Flash???????????????????????????????????????Flash????????????';
                        html += '</div>';
                        $("#lesson-swf-content").html(html);
                        $("#lesson-swf-content").show();
                    } else {
                        $("#lesson-swf-content").html('<div id="lesson-swf-player"></div>');
                        swfobject.embedSWF(lesson.mediaUri, 
                            'lesson-swf-player', '100%', '100%', "9.0.0", null, null, 
                            {wmode:'opaque',allowFullScreen:'true'});
                        $("#lesson-swf-content").show();
                    }

                }


                if (lesson.type == 'testpaper') {
                    that.element.find('[data-role=finish-lesson]').hide();
                } else {
                    if (!that.element.data('hideMediaLessonLearnBtn')) {
                        that.element.find('[data-role=finish-lesson]').show();
                    } else {
                        if (lesson.type == 'video' || lesson.type == 'audio') {
                            that.element.find('[data-role=finish-lesson]').hide();
                        } else {
                            that.element.find('[data-role=finish-lesson]').show();
                        }
                    }
                }

                that._toolbar.set('lesson', lesson);
                that._startLesson();
                that._afterLoadLesson(id);
            }, 'json');

            $.get(this.get('courseUri') + '/lesson/' + id + '/learn/status', function(json) {
                var $finishButton = that.element.find('[data-role=finish-lesson]');
                if (json.status != 'finished') {
                    $finishButton.removeClass('btn-success');
                    $finishButton.attr('disabled',false);
                    $finishButton.find('.glyphicon').removeClass('glyphicon-check').addClass('glyphicon-unchecked');
                } else {
                    $finishButton.addClass('btn-success');
                    $finishButton.attr('disabled',true);
                    $finishButton.find('.glyphicon').removeClass('glyphicon-unchecked').addClass('glyphicon-check');
                }
            }, 'json');

            this._showOrHideNavBtn();

        },

        _showOrHideNavBtn: function() {
            var $prevBtn = this.$('[data-role=prev-lesson]'),
                $nextBtn = this.$('[data-role=next-lesson]'),
                index = $.inArray(parseInt(this.get('lessonId')), this._lessons);
            $prevBtn.show();
            $nextBtn.show();

            if (index < 0) {
                return ;
            }

            if (index === 0) {
                $prevBtn.hide();
            } else if (index === (this._lessons.length - 1)) {
                $nextBtn.hide();
            }

        },

        _getNextLessonId: function(e) {

            var index = $.inArray(parseInt(this.get('lessonId')), this._lessons);
            if (index < 0) {
                return -1;
            }

            if (index + 1 >= this._lessons.length) {
                return -1;
            }

            return this._lessons[index+1];
        },

        _getPrevLessonId: function(e) {
            var index = $.inArray(parseInt(this.get('lessonId')), this._lessons);
            if (index < 0) {
                return -1;
            }

            if (index == 0 ) {
                return -1;
            }

            return this._lessons[index-1];
        },
        _initChapter: function(e) {
           this.chapterAnimate = new chapterAnimate({
            'element': this.element
           });
        },

        _getCountDown: function(days,hours,minutes,seconds){
            $countDown = "??????: <strong class='text-info'>" + days + "</strong>???<strong class='text-info'>" + hours + "</strong>??????<strong class='text-info'>" + minutes + "</strong>??????<strong>" + seconds + "</strong>???<br><br>";

            if (days == 0) {
                $countDown = "??????: <strong class='text-info'>" + hours + "</strong>??????<strong class='text-info'>" + minutes + "</strong>??????<strong class='text-info'>" + seconds + "</strong>???<br><br>";
            };

            if (hours == 0 && days != 0) {
                $countDown = "??????: <strong class='text-info'>" + days + "</strong>???<strong class='text-info'>" + minutes + "</strong>??????<strong class='text-info'>" + seconds + "</strong>???<br><br>";
            };

            if (hours == 0 && days == 0) {
                $countDown = "??????: <strong class='text-info'>" + minutes + "</strong>??????<strong class='text-info'>" + seconds + "</strong>???<br><br>";
            };  

            return $countDown;          
        }

    });

    var Counter = Class.create({
        initialize: function(dashboard, courseId, lessonId, watchLimit) {
            this.dashboard = dashboard;
            this.courseId = courseId;
            this.lessonId = lessonId;
            this.interval = 120;
            this.watched = false;
            this.watchLimit = watchLimit;
        },

        setTimerId: function(timerId) {
            this.timerId = timerId;
        },

        execute: function(){
            var posted = this.addMediaPlayingCounter();
            this.addLearningCounter(posted);
        },

        addLearningCounter: function(promptlyPost) {
            var learningCounter = Store.get("lesson_id_"+this.lessonId+"_learning_counter");
            if(!learningCounter){
                learningCounter = 0;
            }
            learningCounter++;

            if(promptlyPost || learningCounter >= this.interval){
                var url="../../../../course/"+this.lessonId+'/learn/time/'+learningCounter;
                $.get(url);
                learningCounter = 0;
            }

            Store.set("lesson_id_"+this.lessonId+"_learning_counter", learningCounter);
        },

        addMediaPlayingCounter: function() {
            var mediaPlayingCounter = Store.get("lesson_id_"+this.lessonId+"_playing_counter");
            if(!mediaPlayingCounter){
                mediaPlayingCounter = 0;
            }
            if(this.dashboard == undefined || this.dashboard.get("player") == undefined) {
                return;
            }

            var playing = this.dashboard.get("player").playing;
            var posted = false;

            if(mediaPlayingCounter >= this.interval || (mediaPlayingCounter>0 && !playing)){
                var url="../../../../course/"+this.lessonId+'/watch/time/'+mediaPlayingCounter;
                var self = this;
                $.get(url, function(response) {
                    if (self.watchLimit && response.watchLimited) {
                        window.location.reload();
                    }
                }, 'json');
                posted = true;
                mediaPlayingCounter = 0;
            } else if(playing) {
                mediaPlayingCounter++;
            }

            Store.set("lesson_id_"+this.lessonId+"_playing_counter", mediaPlayingCounter);

            return posted;
        }
    });

    exports.run = function() {
        
        var dashboard = new LessonDashboard({
            element: '#lesson-dashboard'
        }).render();
        $(".es-qrcode").click(function(){
            var $this = $(this); 
            var url=document.location.href.split("#");
            var id=url[1].split("/");
            if($this.hasClass('open')) {
                $this.removeClass('open');
            }else {
                $.ajax({
                    type: "post",
                    url: $this.data("url")+"/lesson/"+id[1]+"/qrcode",
                    dataType: "json",
                    success:function(data){
                        $this.find(".qrcode-popover img").attr("src",data.img);
                        $this.addClass('open');
                    }
                });
            }
        });
    };

});