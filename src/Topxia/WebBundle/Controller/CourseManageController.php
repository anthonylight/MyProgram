<?php
namespace Topxia\WebBundle\Controller;

use Topxia\Common\Paginator;
use Topxia\Common\ArrayToolkit;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Topxia\Common\FileToolkit;

class CourseManageController extends BaseController
{
    public function indexAction(Request $request, $id)
    {
        $course = $this->getCourseService()->tryManageCourse($id);

        if ($course['locked'] == '1') {
            return $this->redirect($this->generateUrl('course_manage_course_sync', array('id' => $id, 'type' => 'base')));
        }

        return $this->forward('TopxiaWebBundle:CourseManage:base', array('id' => $id));
    }

    public function baseAction(Request $request, $id)
    {
        $course        = $this->getCourseService()->tryManageCourse($id);
        $courseSetting = $this->getSettingService()->get('course', array());

        if ($request->getMethod() == 'POST') {
            $data = $request->request->all();
            $this->getCourseService()->updateCourse($id, $data);
            $this->setFlashMessage('success', '课程基本信息已保存！');
            return $this->redirect($this->generateUrl('course_manage_base', array('id' => $id)));
        }

        $tags    = $this->getTagService()->findTagsByIds($course['tags']);
        $default = $this->getSettingService()->get('default', array());

        return $this->render('TopxiaWebBundle:CourseManage:base.html.twig', array(
            'course'  => $course,
            'tags'    => ArrayToolkit::column($tags, 'name'),
            'default' => $default
        ));
    }

    public function nicknameCheckAction(Request $request, $courseId)
    {
        $nickname = $request->query->get('value');
        $result   = $this->getUserService()->isNicknameAvaliable($nickname);

        if ($result) {
            $response = array('success' => false, 'message' => '该用户还不存在！');
        } else {
            $user            = $this->getUserService()->getUserByNickname($nickname);
            $isCourseStudent = $this->getCourseService()->isCourseStudent($courseId, $user['id']);

            if ($isCourseStudent) {
                $response = array('success' => false, 'message' => '该用户已是本课程的学员了！');
            } else {
                $response = array('success' => true, 'message' => '');
            }

            $isCourseTeacher = $this->getCourseService()->isCourseTeacher($courseId, $user['id']);

            if ($isCourseTeacher) {
                $response = array('success' => false, 'message' => '该用户是本课程的教师，不能添加!');
            }
        }

        return $this->createJsonResponse($response);
    }

    public function detailAction(Request $request, $id)
    {
        $course = $this->getCourseService()->tryManageCourse($id);

        if ($request->getMethod() == 'POST') {
            $detail              = $request->request->all();
            $detail['goals']     = (empty($detail['goals']) || !is_array($detail['goals'])) ? array() : $detail['goals'];
            $detail['audiences'] = (empty($detail['audiences']) || !is_array($detail['audiences'])) ? array() : $detail['audiences'];
            $detail['travelDetail'] = empty($detail['data']) ? null : json_encode($detail['data']);



            $this->getCourseService()->updateCourse($id, $detail);
            if($course['type']=='product'){
                $this->setFlashMessage('success', '商品详细信息已保存！');
            }else{
                $this->setFlashMessage('success', '课程详细信息已保存！');
            }


            return $this->redirect($this->generateUrl('course_manage_detail', array('id' => $id)));
        }


        if($course['type']=='travel'){

            $baseJson=json_decode('{"items":{"carousel":{"title":"轮播图","desc":"建议图片大小为1520*520，最多可设置５张图片","count":5,"type":"imglink","default":[{"src":"/themes/language/img/poster_img.jpg","alt":"默认轮播图1","href":"http://www.edusoho.com","target":"_blank"},{"src":"/themes/language/img/poster_img.jpg","alt":"默认轮播图2","href":"http://www.edusoho.com","target":"_blank"},{"src":"/themes/language/img/poster_img.jpg","alt":"默认轮播图3","href":"http://www.edusoho.com","target":"_blank"}]}}}',true);

            $data=json_decode($course['travelDetail'],true);
            if(is_null($data)){
                $data=json_decode('{"carousel":[{"title":"\u7b2c\u4e00\u5929","locationA":"\u5317\u4eac","locationB":"\u6b66\u6c49","locationC":"\u6d1b\u6749\u77f6","src":"\/themes\/language\/img\/poster_img.jpg","alt":"\u7b2c\u4e00\u5929\u7684\u63cf\u8ff0"},{"title":"\u7b2c\u4e8c\u5929","locationA":"","locationB":"","locationC":"","src":"\/themes\/language\/img\/poster_img.jpg","alt":"\u7b2c\u4e8c\u5929\u63cf\u8ff0"},{"title":"\u7b2c\u4e09\u5929","locationA":"","locationB":"","locationC":"","src":"\/themes\/language\/img\/poster_img.jpg","alt":"\u7b2c\u4e09\u5929\u63cf\u8ff0"}]}',true);
            }
           return $this->render('TopxiaWebBundle:CourseManage:detail.html.twig', array(
                'course' => $course,
                'block'  => $baseJson,
                'data' =>$data
            ));
        }

        if($course['type']=='product'){

            $baseJson=json_decode('{"items":{"carousel":{"title":"轮播图","desc":"建议图片大小为1520*520，最多可设置５张图片","count":5,"type":"imglink","default":[{"src":"/themes/language/img/poster_img.jpg","alt":"默认轮播图1","href":"http://www.edusoho.com","target":"_blank"},{"src":"/themes/language/img/poster_img.jpg","alt":"默认轮播图2","href":"http://www.edusoho.com","target":"_blank"},{"src":"/themes/language/img/poster_img.jpg","alt":"默认轮播图3","href":"http://www.edusoho.com","target":"_blank"}]}}}',true);

            $data=json_decode($course['travelDetail'],true);
            if(is_null($data)){
                $data=json_decode('{"carousel":[{"title":"XXL","number":"10","originPrice":"0","price":"0"}]}',true);
            }


            return $this->render('TopxiaWebBundle:CourseManage:detail.html.twig', array(
                'course' => $course,
                'block'  => $baseJson,
                'data' =>$data
            ));
        }

        return $this->render('TopxiaWebBundle:CourseManage:detail.html.twig', array(
            'course' => $course
        ));
    }

    public function uploadAction(Request $request)
    {
        $response = array();
        if ($request->getMethod() == 'POST') {
            $file = $request->files->get('file');
            if (!FileToolkit::isImageFile($file)) {
                throw $this->createAccessDeniedException('图片格式不正确！');
            }

            $filename = 'block_picture_' . time() . '.' . $file->getClientOriginalExtension();

            $directory = "{$this->container->getParameter('topxia.upload.public_directory')}/system";
            $file->move($directory, $filename);
            $url = "{$this->container->getParameter('topxia.upload.public_url_path')}/system/{$filename}";

            $response = array(
                'url' => $url,
            );
        }
        return $this->createJsonResponse($response);
    }

    public function pictureAction(Request $request, $id)
    {
        $course = $this->getCourseService()->tryManageCourse($id);

        return $this->render('TopxiaWebBundle:CourseManage:picture.html.twig', array(
            'course' => $course
        ));
    }

    public function pictureCropAction(Request $request, $id)
    {
        $course = $this->getCourseService()->tryManageCourse($id);

        if ($request->getMethod() == 'POST') {
            $data = $request->request->all();
            $this->getCourseService()->changeCoursePicture($course['id'], $data["images"]);
            return $this->redirect($this->generateUrl('course_manage_picture', array('id' => $course['id'])));
        }

        $fileId                                      = $request->getSession()->get("fileId");
        list($pictureUrl, $naturalSize, $scaledSize) = $this->getFileService()->getImgFileMetaInfo($fileId, 480, 270);

        return $this->render('TopxiaWebBundle:CourseManage:picture-crop.html.twig', array(
            'course'      => $course,
            'pictureUrl'  => $pictureUrl,
            'naturalSize' => $naturalSize,
            'scaledSize'  => $scaledSize
        ));
    }

    public function priceAction(Request $request, $id)
    {
        $course = $this->getCourseService()->tryManageCourse($id);

        $canModifyPrice     = true;
        $teacherModifyPrice = $this->setting('course.teacher_modify_price', true);
//        var_dump($teacherModifyPrice);exit;

        if (empty($teacherModifyPrice)) {
            if (!$this->getCurrentUser()->isAdmin()) {
                $canModifyPrice = false;
                goto response;
            }
        }

        if ($request->getMethod() == 'POST') {
            $fields = $request->request->all();

            if (isset($fields['coinPrice'])) {
                $this->getCourseService()->setCoursePrice($course['id'], 'coin', $fields['coinPrice']);
                unset($fields['coinPrice']);
            }

            if (isset($fields['price'])) {
                $this->getCourseService()->setCoursePrice($course['id'], 'default', $fields['price']);
                unset($fields['price']);
            }


            if (isset($fields['costPrice'])) {
                $this->getCourseService()->setCourseCostPrice($course['id'], 'default', $fields['costPrice']);
                unset($fields['costPrice']);
            }


            if (!empty($fields)) {
                $course = $this->getCourseService()->updateCourse($id, $fields);
            } else {
                $course = $this->getCourseService()->getCourse($id);
            }

            $this->setFlashMessage('success', '课程价格已经修改成功!');
        }

        response:

        if ($this->isPluginInstalled("Vip") && $this->setting('vip.enabled')) {
            $levels = $this->getLevelService()->findEnabledLevels();
        } else {
            $levels = array();
        }

        if (($course['discountId'] > 0) && ($this->isPluginInstalled("Discount"))) {
            $discount = $this->getDiscountService()->getDiscount($course['discountId']);
        } else {
            $discount = null;
        }

        return $this->render('TopxiaWebBundle:CourseManage:price.html.twig', array(
            'course'         => $course,
            'canModifyPrice' => $canModifyPrice,
            'levels'         => $this->makeLevelChoices($levels),
            'discount'       => $discount
        ));
    }

    public function dataAction($id)
    {
        $course = $this->getCourseService()->tryManageCourse($id);

        $isLearnedNum = $this->getCourseService()->searchMemberCount(array('isLearned' => 1, 'courseId' => $id));

        $learnTime = $this->getCourseService()->searchLearnTime(array('courseId' => $id));
        $learnTime = $course["studentNum"] == 0 ? 0 : intval($learnTime / $course["studentNum"]);

        $noteCount = $this->getNoteService()->searchNoteCount(array('courseId' => $id));

        $questionCount = $this->getThreadService()->searchThreadCount(array('courseId' => $id, 'type' => 'question'));

        $lessons = $this->getCourseService()->searchLessons(array('courseId' => $id), array('createdTime', 'ASC'), 0, 1000);

        foreach ($lessons as $key => $value) {
            $lessonLearnedNum = $this->getCourseService()->findLearnsCountByLessonId($value['id']);

            $finishedNum = $this->getCourseService()->searchLearnCount(array('status' => 'finished', 'lessonId' => $value['id']));

            $lessonLearnTime = $this->getCourseService()->searchLearnTime(array('lessonId' => $value['id']));
            $lessonLearnTime = $lessonLearnedNum == 0 ? 0 : intval($lessonLearnTime / $lessonLearnedNum);

            $lessonWatchTime = $this->getCourseService()->searchWatchTime(array('lessonId' => $value['id']));
            $lessonWatchTime = $lessonWatchTime == 0 ? 0 : intval($lessonWatchTime / $lessonLearnedNum);

            $lessons[$key]['LearnedNum']  = $lessonLearnedNum;
            $lessons[$key]['length']      = intval($lessons[$key]['length'] / 60);
            $lessons[$key]['finishedNum'] = $finishedNum;
            $lessons[$key]['learnTime']   = $lessonLearnTime;
            $lessons[$key]['watchTime']   = $lessonWatchTime;

            if ($value['type'] == 'testpaper') {
                $paperId  = $value['mediaId'];
                $score    = $this->getTestpaperService()->searchTestpapersScore(array('testId' => $paperId));
                $paperNum = $this->getTestpaperService()->searchTestpaperResultsCount(array('testId' => $paperId));

                $lessons[$key]['score'] = $finishedNum == 0 ? 0 : intval($score / $paperNum);
            }
        }

        return $this->render('TopxiaWebBundle:CourseManage:learning-data.html.twig', array(
            'course'        => $course,
            'isLearnedNum'  => $isLearnedNum,
            'learnTime'     => $learnTime,
            'noteCount'     => $noteCount,
            'questionCount' => $questionCount,
            'lessons'       => $lessons
        ));
    }

    public function orderAction(Request $request, $id)
    {
        $this->getCourseService()->tryManageCourse($id);

        $courseSetting = $this->setting("course");

        if (!$this->getCurrentUser()->isAdmin() && (empty($courseSetting["teacher_search_order"]) || $courseSetting["teacher_search_order"] != 1)) {
            throw $this->createAccessDeniedException("查询订单已关闭，请联系管理员");
        }

        $conditions               = $request->query->all();
        $type                     = 'course';
        $conditions['targetType'] = $type;

        if (isset($conditions['keywordType'])) {
            $conditions[$conditions['keywordType']] = trim($conditions['keyword']);
        }

        $conditions['targetId'] = $id;
        $course                 = $this->getCourseService()->tryManageCourse($id);

        if($course['type']=='product'){
            $conditions['targetType'] = 'product';
        }

        if (!empty($conditions['startDateTime']) && !empty($conditions['endDateTime'])) {
            $conditions['startTime'] = strtotime($conditions['startDateTime']);
            $conditions['endTime']   = strtotime($conditions['endDateTime']);
        }

        $paginator = new Paginator(
            $request,
            $this->getOrderService()->searchOrderCount($conditions),
            10
        );

        $orders = $this->getOrderService()->searchOrders(
            $conditions,
            'latest',
            $paginator->getOffsetCount(),
            $paginator->getPerPageCount()
        );

        $users = $this->getUserService()->findUsersByIds(ArrayToolkit::column($orders, 'userId'));

        foreach ($orders as $index => $expiredOrderToBeUpdated) {
            if ((($expiredOrderToBeUpdated["createdTime"] + 48 * 60 * 60) < time()) && ($expiredOrderToBeUpdated["status"] == 'created')) {
                $this->getOrderService()->cancelOrder($expiredOrderToBeUpdated['id']);
                $orders[$index]['status'] = 'cancelled';
            }
        }

        return $this->render('TopxiaWebBundle:CourseManage:course-order.html.twig', array(
            'course'    => $course,
            'request'   => $request,
            'orders'    => $orders,
            'users'     => $users,
            'paginator' => $paginator
        ));
    }

    public function orderExportCsvAction(Request $request, $id)
    {
        $this->getCourseService()->tryManageCourse($id);

        $courseSetting = $this->setting("course");

        if (!$this->getCurrentUser()->isAdmin() && (empty($courseSetting["teacher_search_order"]) || $courseSetting["teacher_search_order"] != 1)) {
            throw $this->createAccessDeniedException("查询订单已关闭，请联系管理员");
        }

        $status  = array('created' => '未付款', 'paid' => '已付款', 'refunding' => '退款中', 'refunded' => '已退款', 'cancelled' => '已关闭');
        $payment = array('alipay' => '支付宝', 'wxpay' => '微信支付', 'cion' => '虚拟币支付', 'none' => '--');

        $conditions = $request->query->all();

        $type                     = 'course';
        $conditions['targetType'] = $type;

        if (isset($conditions['keywordType'])) {
            $conditions[$conditions['keywordType']] = trim($conditions['keyword']);
        }
        $course = $this->getCourseService()->getCourse($id);

        if($course['type']=='product'){
            $conditions['targetType'] = 'product';
        }

        $conditions['targetId'] = $id;

        if (!empty($conditions['startDateTime']) && !empty($conditions['endDateTime'])) {
            $conditions['startTime'] = strtotime($conditions['startDateTime']);
            $conditions['endTime']   = strtotime($conditions['endDateTime']);
        }

        $orders = $this->getOrderService()->searchOrders(
            $conditions,
            'latest',
            0,
            PHP_INT_MAX
        );

        $userinfoFields = array('sn', 'createdTime', 'status', 'targetType', 'amount', 'payment', 'paidTime');

        $studentUserIds = ArrayToolkit::column($orders, 'userId');

        $users = $this->getUserService()->findUsersByIds($studentUserIds);
        $users = ArrayToolkit::index($users, 'id');



        $str = "订单号,名称,创建时间,状态,实际付款,购买者,支付方式,支付时间";

        $str .= "\r\n";

        $results = array();

        foreach ($orders as $key => $orders) {
            $column = "";
            $column .= $orders['sn'].",";
            $column .= $orders['title'].",";
            $column .= date('Y-n-d H:i:s', $orders['createdTime']).",";
            $column .= $status[$orders['status']].",";
            $column .= $orders['amount'].",";
            $column .= $users[$orders['userId']]['nickname'].",";
            $column .= $payment[$orders['payment']].",";

            if ($orders['paidTime'] == 0) {
                $column .= "-".",";
            } else {
                $column .= date('Y-n-d H:i:s', $orders['paidTime']).",";
            }

            $results[] = $column;
        }

        $str .= implode("\r\n", $results);
        $str = chr(239).chr(187).chr(191).$str;

        $filename = sprintf("course-%s-orders-(%s).csv", $course['title'], date('Y-n-d'));

        $response = new Response();
        $response->headers->set('Content-type', 'text/csv');
        $response->headers->set('Content-Disposition', 'attachment; filename="'.$filename.'"');
        $response->headers->set('Content-length', strlen($str));
        $response->setContent($str);

        return $response;
    }

    protected function makeLevelChoices($levels)
    {
        $choices = array();

        foreach ($levels as $level) {
            $choices[$level['id']] = $level['name'];
        }

        return $choices;
    }

    public function teachersAction(Request $request, $id)
    {

        $course = $this->getCourseService()->tryManageCourse($id);

        if ($request->getMethod() == 'POST') {
            $data        = $request->request->all();
            $data['ids'] = empty($data['ids']) ? array() : array_values($data['ids']);

            $teachers = array();

            foreach ($data['ids'] as $teacherId) {
                $teachers[] = array(
                    'id'        => $teacherId,
                    'isVisible' => empty($data['visible_'.$teacherId]) ? 0 : 1
                );
            }

            $this->getCourseService()->setCourseTeachers($id, $teachers);

            $classroomIds = $this->getClassroomService()->findClassroomIdsByCourseId($id);

            if ($classroomIds) {
                $this->getClassroomService()->updateClassroomTeachers($classroomIds[0]);
            }

            $this->setFlashMessage('success', '教师设置成功！');

            return $this->redirect($this->generateUrl('course_manage_teachers', array('id' => $id)));
        }

        $teacherMembers = $this->getCourseService()->findCourseTeachers($id);

        $users = $this->getUserService()->findUsersByIds(ArrayToolkit::column($teacherMembers, 'userId'));

        $teachers = array();

        foreach ($teacherMembers as $member) {
            if (empty($users[$member['userId']])) {
                continue;
            }

            $teachers[] = array(
                'id'        => $member['userId'],
                'nickname'  => $users[$member['userId']]['nickname'],
                'avatar'    => $this->getWebExtension()->getFilePath($users[$member['userId']]['smallAvatar'], 'avatar.png'),
                'isVisible' => $member['isVisible'] ? true : false
            );
        }

        return $this->render('TopxiaWebBundle:CourseManage:teachers.html.twig', array(
            'course'   => $course,
            'teachers' => $teachers
        ));
    }


    public function coursesAction(Request $request, $id)
    {
        $travelCourse = $this->getCourseService()->tryManageCourse($id);


//        $this->getClassroomService()->tryManageClassroom($id);

        $userIds   = array();
        $coinPrice = 0;
        $price     = 0;

//        $classroom = $this->getClassroomService()->getClassroom($id);

        if ($request->getMethod() == 'POST') {
            $courseIds = $request->request->get('courseIds');

            if (empty($courseIds)) {
                $courseIds = array();
            }

            $this->getClassroomService()->updateTravelCourses($id, $courseIds);

            $this->setFlashMessage('success', "课程修改成功");

            return $this->redirect($this->generateUrl('travel_manage_courses', array(
                'id' => $id
            )));
        }

        $courses = $this->getClassroomService()->findActiveCoursesByTravelId($id);

//        var_dump($courses);exit;


        foreach ($courses as $course) {
            $userIds = array_merge($userIds, $course['teacherIds']);

            $coinPrice += $course['coinPrice'];
            $price += $course['price'];
        }

        $users = $this->getUserService()->findUsersByIds($userIds);

        return $this->render("TopxiaWebBundle:CourseManage:courses.html.twig", array(
//            'classroom' => $classroom,
            'course'    => $travelCourse,
            'courses'   => $courses,
            'price'     => $price,
            'coinPrice' => $coinPrice,
            'users'     => $users));
    }

    public function pickAction(Request $request, $id)
    {
        $travelCourse = $this->getCourseService()->tryManageCourse($id);
        $actviteCourses = $this->getClassroomService()->findActiveCoursesByTravelId($id);

        $excludeIds = ArrayToolkit::column($actviteCourses, 'id');
        if(!in_array($id,$excludeIds)){
            $excludeIds[]= $id;
        }



//        var_dump($excludeIds);exit;

        $conditions = array(
            'status' => 'published',
            'parentId' => 0,
            'excludeIds' => $excludeIds,
            'excludeTypes'=>array('travel','product')
        );

        $paginator = new Paginator(
            $this->get('request'),
            $this->getCourseService()->searchCourseCount($conditions),
            5
        );

        $courses = $this->getCourseService()->searchCourses(
            $conditions,
            'latest',
            $paginator->getOffsetCount(),
            $paginator->getPerPageCount()
        );


        $courseIds = ArrayToolkit::column($courses, 'id');
        $userIds = array();
        foreach ($courses as &$course) {
            $course['tags'] = $this->getTagService()->findTagsByIds($course['tags']);
            $userIds = array_merge($userIds, $course['teacherIds']);
        }

        $users = $this->getUserService()->findUsersByIds($userIds);

        return $this->render("TopxiaWebBundle:CourseManage:course-pick-modal.html.twig", array(
            'users' => $users,
            'courses' => $courses,
            'course' =>$travelCourse,
//            'classroomId' => $classroomId,
            'paginator' => $paginator,
        ));
    }

    public function searchAction(Request $request, $id)
    {
        $travelCourse = $this->getCourseService()->tryManageCourse($id);
        $key = $request->request->get("key");

        $conditions = array("title" => $key,'excludeTypes'=>array('travel','product'));
        $conditions['status'] = 'published';
        $conditions['parentId'] = 0;
        $courses = $this->getCourseService()->searchCourses(
            $conditions,
            'latest',
            0,
            5
        );

        $courseIds = ArrayToolkit::column($courses, 'id');

        $userIds = array();
        foreach ($courses as &$course) {
            $course['tags'] = $this->getTagService()->findTagsByIds($course['tags']);
            $userIds = array_merge($userIds, $course['teacherIds']);
        }

        $users = $this->getUserService()->findUsersByIds($userIds);

        return $this->render('TopxiaWebBundle:Course:course-select-list.html.twig', array(
            'users' => $users,
            'courses' => $courses,
        ));
    }


    public function coursesSelectAction(Request $request, $id)
    {
        $travelCourse = $this->getCourseService()->tryManageCourse($id);

        $data = $request->request->all();
        $ids  = array();

        if (isset($data['ids']) && $data['ids'] != "") {
            $ids = $data['ids'];
            $ids = explode(",", $ids);
        } else {
            return new Response('success');
        }


        $this->getClassroomService()->addCoursesToTravel($id, $ids);
        $this->setFlashMessage('success', "课程添加成功");

        return new Response('success');
    }


    public function publishAction(Request $request, $id)
    {
        $this->getCourseService()->publishCourse($id);
        return $this->createJsonResponse(true);
    }

    public function teachersMatchAction(Request $request)
    {
        $likeString = $request->query->get('q');
        $users      = $this->getUserService()->searchUsers(array('nickname' => $likeString, 'roles' => 'ROLE_TEACHER'), array('createdTime', 'DESC'), 0, 10);

        $teachers = array();

        foreach ($users as $user) {
            $teachers[] = array(
                'id'        => $user['id'],
                'nickname'  => $user['nickname'],
                'avatar'    => $this->getWebExtension()->getFilePath($user['smallAvatar'], 'avatar.png'),
                'isVisible' => 1
            );
        }

        return $this->createJsonResponse($teachers);
    }

    #课程同步
    public function courseSyncAction(Request $request, $id, $type)
    {
        $courseId     = $id;
        $course       = $this->getCourseService()->getCourse($courseId);
        $parentCourse = $this->getCourseService()->getCourse($course['parentId']);
        $type         = $type;
        $title        = '';
        $url          = '';

        switch ($type) {
            case 'base':
                $title = '基本信息';
                $url   = 'course_manage_base';
                break;
            case 'detail':
                $title = '详细信息';
                $url   = 'course_manage_detail';
                break;
            case 'picture':
                $title = '课程图片';
                $url   = 'course_manage_picture';
                break;
            case 'lesson':
                $title = '课时管理';
                $url   = 'course_manage_lesson';
                break;
            case 'files':
                $title = '文件管理';
                $url   = 'course_manage_files';
                break;
            case 'replay':
                $title = '录播管理';
                $url   = 'live_course_manage_replay';
                break;
            case 'price':
                $title = '价格设置';
                $url   = 'course_manage_price';
                break;
            case 'teachers':
                $title = '教师设置';
                $url   = 'course_manage_teachers';
                break;
            case 'question':
                $title = '题目管理';
                $url   = 'course_manage_question';
                break;
            case 'question_plumber':
                $title = '题目导入/导出';
                $url   = 'course_question_plumber';
                break;
            case 'testpaper':
                $title = '试卷管理';
                $url   = 'course_manage_testpaper';
                break;
            default:
                $title = '未知页面';
                $url   = '';
                break;
        }

        $course = $this->getCourseService()->tryManageCourse($courseId);
        return $this->render('TopxiaWebBundle:CourseManage:courseSync.html.twig', array(
            'course'       => $course,
            'type'         => $type,
            'title'        => $title,
            'url'          => $url,
            'parentCourse' => $parentCourse
        ));
    }

    public function courseSyncEditAction(Request $request)
    {
        $courseId = $request->query->get('courseId');
        $course   = $this->getCourseService()->getCourse($courseId);
        $type     = $request->query->get('type');
        $url      = $request->query->get('url');

        if ($request->getMethod() == 'POST') {
            $courseId = $request->request->get('courseId');
            $url      = $request->request->get('url');
            $course   = $this->getCourseService()->getCourse($courseId);

            if ($course['locked'] == 1) {
                $this->getCourseService()->updateCourse($courseId, array('locked' => 0));
            }

            return $this->createJsonResponse($url);
        }

        return $this->render('TopxiaWebBundle:CourseManage:courseSyncEdit.html.twig', array(
            'course' => $course,
            'type'   => $type,
            'url'    => $url
        ));
    }

    protected function getCourseService()
    {
        return $this->getServiceKernel()->createService('Course.CourseService');
    }

    protected function getLevelService()
    {
        return $this->getServiceKernel()->createService('Vip:Vip.LevelService');
    }

    protected function getFileService()
    {
        return $this->getServiceKernel()->createService('Content.FileService');
    }

    protected function getWebExtension()
    {
        return $this->container->get('topxia.twig.web_extension');
    }

    protected function getTagService()
    {
        return $this->getServiceKernel()->createService('Taxonomy.TagService');
    }

    protected function getNoteService()
    {
        return $this->getServiceKernel()->createService('Course.NoteService');
    }

    protected function getThreadService()
    {
        return $this->getServiceKernel()->createService('Course.ThreadService');
    }

    protected function getTestpaperService()
    {
        return $this->getServiceKernel()->createService('Testpaper.TestpaperService');
    }

    protected function getSettingService()
    {
        return $this->getServiceKernel()->createService('System.SettingService');
    }

    protected function getClassroomService()
    {
        return $this->getServiceKernel()->createService('Classroom:Classroom.ClassroomService');
    }

    protected function getDiscountService()
    {
        return $this->getServiceKernel()->createService('Discount:Discount.DiscountService');
    }

    protected function getOrderService()
    {
        return $this->getServiceKernel()->createService('Order.OrderService');
    }

    protected function getUserFieldService()
    {
        return $this->getServiceKernel()->createService('User.UserFieldService');
    }
}
