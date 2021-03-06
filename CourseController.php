<?php
namespace Topxia\AdminBundle\Controller;

use Topxia\Common\Paginator;
use Topxia\Common\ArrayToolkit;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Security\Core\Encoder\MessageDigestPasswordEncoder;

class CourseController extends BaseController
{
    public function indexAction(Request $request, $filter)
    {
        $conditions = $request->query->all();

        if ($filter == 'normal' || $filter == 'travel') {
            $conditions["parentId"] = 0;
        }

        if ($filter == 'travel') {
            $conditions["type"] = 'travel';
        }

        if ($filter == 'product') {
            $conditions["type"] = 'product';
        }


        if ($filter == 'normal') {
            $conditions['excludeTypes']=array('travel','product');
        }

        if ($filter == 'classroom') {
            $conditions["parentId_GT"] = 0;
        }

        if (isset($conditions["categoryId"]) && $conditions["categoryId"] == "") {
            unset($conditions["categoryId"]);
        }

        if (isset($conditions["status"]) && $conditions["status"] == "") {
            unset($conditions["status"]);
        }

        if (isset($conditions["title"]) && $conditions["title"] == "") {
            unset($conditions["title"]);
        }

        if (isset($conditions["creator"]) && $conditions["creator"] == "") {
            unset($conditions["creator"]);
        }

        $coinSetting = $this->getSettingService()->get("coin");
        $coinEnable  = isset($coinSetting["coin_enabled"]) && $coinSetting["coin_enabled"] == 1 && $coinSetting['cash_model'] == 'currency';

        if (isset($conditions["chargeStatus"]) && $conditions["chargeStatus"] == "free") {
            if ($coinEnable) {
                $conditions['coinPrice'] = '0.00';
            } else {
                $conditions['price'] = '0.00';
            }
        }

        if (isset($conditions["chargeStatus"]) && $conditions["chargeStatus"] == "charge") {
            if ($coinEnable) {
                $conditions['coinPrice_GT'] = '0.00';
            } else {
                $conditions['price_GT'] = '0.00';
            }
        }

        $count = $this->getCourseService()->searchCourseCount($conditions);

        $paginator = new Paginator($this->get('request'), $count, 20);
        $courses   = $this->getCourseService()->searchCourses(
            $conditions,
            null,
            $paginator->getOffsetCount(),
            $paginator->getPerPageCount()
        );

        $classrooms = array();

        if ($filter == 'classroom') {
            $classrooms = $this->getClassroomService()->findClassroomsByCoursesIds(ArrayToolkit::column($courses, 'id'));
            $classrooms = ArrayToolkit::index($classrooms, 'courseId');

            foreach ($classrooms as $key => $classroom) {
                $classroomInfo                      = $this->getClassroomService()->getClassroom($classroom['classroomId']);
                $classrooms[$key]['classroomTitle'] = $classroomInfo['title'];
            }
        }

        $categories = $this->getCategoryService()->findCategoriesByIds(ArrayToolkit::column($courses, 'categoryId'));

        $users = $this->getUserService()->findUsersByIds(ArrayToolkit::column($courses, 'userId'));

        $courseSetting = $this->getSettingService()->get('course', array());

        if (!isset($courseSetting['live_course_enabled'])) {
            $courseSetting['live_course_enabled'] = "";
        }

        $default = $this->getSettingService()->get('default', array());

        return $this->render('TopxiaAdminBundle:Course:index.html.twig', array(
            'conditions'     => $conditions,
            'courses'        => $courses,
            'users'          => $users,
            'categories'     => $categories,
            'paginator'      => $paginator,
            'liveSetEnabled' => $courseSetting['live_course_enabled'],
            'default'        => $default,
            'classrooms'     => $classrooms,
            'filter'         => $filter
        ));
    }

    protected function searchFuncUsedBySearchActionAndSearchToFillBannerAction(Request $request, $twigToRender)
    {
        $key = $request->request->get("key");

        $conditions           = array("title" => $key);
        $conditions['status'] = 'published';
        $conditions['type']   = 'normal';

        $count = $this->getCourseService()->searchCourseCount($conditions);

        $paginator = new Paginator($this->get('request'), $count, 6);

        $courses = $this->getCourseService()->searchCourses(
            $conditions,
            null,
            $paginator->getOffsetCount(),
            $paginator->getPerPageCount()
        );

        $categories = $this->getCategoryService()->findCategoriesByIds(ArrayToolkit::column($courses, 'categoryId'));

        $users = $this->getUserService()->findUsersByIds(ArrayToolkit::column($courses, 'userId'));

        return $this->render($twigToRender, array(
            'key'        => $key,
            'courses'    => $courses,
            'users'      => $users,
            'categories' => $categories,
            'paginator'  => $paginator
        ));
    }

    public function searchAction(Request $request)
    {
        return $this->searchFuncUsedBySearchActionAndSearchToFillBannerAction($request, 'TopxiaAdminBundle:Course:search.html.twig');
    }

    public function searchToFillBannerAction(Request $request)
    {
        return $this->searchFuncUsedBySearchActionAndSearchToFillBannerAction($request, 'TopxiaAdminBundle:Course:search-to-fill-banner.html.twig');
    }

    /*
    code ????????????
    1:?????????????????????
    2: ??????????????????
    0: ???????????????????????????
     */
    public function deleteAction(Request $request, $courseId, $type)
    {
        $currentUser = $this->getCurrentUser();

        if (!$currentUser->isSuperAdmin()) {
            throw $this->createAccessDeniedException('???????????????????????????');
        }

        $course = $this->getCourseService()->getCourse($courseId);

        if ($course['status'] == 'published') {
            throw $this->createAccessDeniedException('??????????????????????????????');
        }

        $subCourses = $this->getCourseService()->findCoursesByParentIdAndLocked($courseId, 1);

        if (!empty($subCourses)) {
            return $this->createJsonResponse(array('code' => 2, 'message' => '????????????????????????'));
        }

        if ($course['status'] == 'draft') {
            $result = $this->getCourseService()->deleteCourse($courseId);
            return $this->createJsonResponse(array('code' => 0, 'message' => '??????????????????'));
        }

        if ($course['status'] == 'closed') {
            $classroomCourse = $this->getClassroomService()->findClassroomIdsByCourseId($course['id']);

            if ($classroomCourse) {
                return $this->createJsonResponse(array('code' => 3, 'message' => '?????????????????????,????????????????????????'));
            }

            //???????????????????????????
            $homework = $this->getAppService()->findInstallApp("Homework");

            if (!empty($homework)) {
                $isDeleteHomework = $homework && version_compare($homework['version'], "1.3.1", ">=");

                if (!$isDeleteHomework) {
                    return $this->createJsonResponse(array('code' => 1, 'message' => '?????????????????????'));
                }
            }

            if ($type) {
                $isCheckPassword = $request->getSession()->get('checkPassword');

                if (!$isCheckPassword) {
                    throw $this->createAccessDeniedException('?????????????????????????????????');
                }

                $result = $this->getCourseDeleteService()->delete($courseId, $type);
                return $this->createJsonResponse($this->returnDeleteStatus($result, $type));
            }
        }

        return $this->render('TopxiaAdminBundle:Course:delete.html.twig', array('course' => $course));
    }

    public function checkPasswordAction(Request $request)
    {
        if ($request->getMethod() == 'POST') {
            $password    = $request->request->get('password');
            $currentUser = $this->getCurrentUser();
            $password    = $this->getPasswordEncoder()->encodePassword($password, $currentUser->salt);

            if ($password == $currentUser->password) {
                $response = array('success' => true, 'message' => '????????????');
                $request->getSession()->set('checkPassword', true);
            } else {
                $response = array('success' => false, 'message' => '????????????');
            }

            return $this->createJsonResponse($response);
        }
    }

    public function publishAction(Request $request, $id)
    {
        $this->getCourseService()->publishCourse($id);

        return $this->renderCourseTr($id, $request);
    }

    public function closeAction(Request $request, $id)
    {
        $this->getCourseService()->closeCourse($id);

        return $this->renderCourseTr($id, $request);
    }

    public function copyAction(Request $request, $id)
    {
        $course = $this->getCourseService()->getCourse($id);

        return $this->render('TopxiaAdminBundle:Course:copy.html.twig', array(
            'course' => $course
        ));
    }

    public function copingAction(Request $request, $id)
    {
        $course = $this->getCourseService()->getCourse($id);

        $conditions      = $request->request->all();
        $course['title'] = $conditions['title'];

        $this->getCourseCopyService()->copy($course);

        return $this->redirect($this->generateUrl('admin_course'));
    }

    public function recommendAction(Request $request, $id)
    {
        $course = $this->getCourseService()->getCourse($id);

        $ref    = $request->query->get('ref');
        $filter = $request->query->get('filter');

        if ($request->getMethod() == 'POST') {
            $number = $request->request->get('number');

            $course = $this->getCourseService()->recommendCourse($id, $number);

            $user = $this->getUserService()->getUser($course['userId']);

            if ($ref == 'recommendList') {
                return $this->render('TopxiaAdminBundle:Course:course-recommend-tr.html.twig', array(
                    'course' => $course,
                    'user'   => $user
                ));
            }

            return $this->renderCourseTr($id, $request);
        }

        return $this->render('TopxiaAdminBundle:Course:course-recommend-modal.html.twig', array(
            'course' => $course,
            'ref'    => $ref,
            'filter' => $filter
        ));
    }

    public function cancelRecommendAction(Request $request, $id, $target)
    {
        $course = $this->getCourseService()->cancelRecommendCourse($id);

        if ($target == 'recommend_list') {
            return $this->forward('TopxiaAdminBundle:Course:recommendList', array(
                'request' => $request
            ));
        }

        if ($target == 'normal_index') {
            return $this->renderCourseTr($id, $request);
        }
    }

    public function recommendListAction(Request $request)
    {
        $conditions                = $request->query->all();
        $conditions['status']      = 'published';
        $conditions['recommended'] = 1;

        $paginator = new Paginator(
            $this->get('request'),
            $this->getCourseService()->searchCourseCount($conditions),
            20
        );

        $courses = $this->getCourseService()->searchCourses(
            $conditions,
            'recommendedSeq',
            $paginator->getOffsetCount(),
            $paginator->getPerPageCount()
        );

        $users = $this->getUserService()->findUsersByIds(ArrayToolkit::column($courses, 'userId'));

        $categories = $this->getCategoryService()->findCategoriesByIds(ArrayToolkit::column($courses, 'categoryId'));

        return $this->render('TopxiaAdminBundle:Course:course-recommend-list.html.twig', array(
            'courses'    => $courses,
            'users'      => $users,
            'paginator'  => $paginator,
            'categories' => $categories
        ));
    }

    public function categoryAction(Request $request)
    {
        return $this->forward('TopxiaAdminBundle:Category:embed', array(
            'group'  => 'course',
            'layout' => 'TopxiaAdminBundle::layout.html.twig'
        ));
    }


    public function productCategoryAction(Request $request)
    {
        return $this->forward('TopxiaAdminBundle:Category:embed', array(
            'group'  => 'product',
            'layout' => 'TopxiaAdminBundle::layout.html.twig'
        ));
    }


    public function travelCategoryAction(Request $request)
    {
        return $this->forward('TopxiaAdminBundle:Category:embed', array(
            'group'  => 'travel',
            'layout' => 'TopxiaAdminBundle::layout.html.twig'
        ));
    }

    public function dataAction(Request $request)
    {
        $cond = array('type' => 'normal');

        $conditions = $request->query->all();

        $conditions = array_merge($cond, $conditions);
        $count      = $this->getCourseService()->searchCourseCount($conditions);

        $paginator = new Paginator($this->get('request'), $count, 20);

        $courses = $this->getCourseService()->searchCourses(
            $conditions,
            null,
            $paginator->getOffsetCount(),
            $paginator->getPerPageCount()
        );

        foreach ($courses as $key => $course) {
            $isLearnedNum = $this->getCourseService()->searchMemberCount(array('isLearned' => 1, 'courseId' => $course['id']));

            $learnTime = $this->getCourseService()->searchLearnTime(array('courseId' => $course['id']));

            $lessonCount = $this->getCourseService()->searchLessonCount(array('courseId' => $course['id']));

            $courses[$key]['isLearnedNum'] = $isLearnedNum;
            $courses[$key]['learnTime']    = $learnTime;
            $courses[$key]['lessonCount']  = $lessonCount;
        }

        return $this->render('TopxiaAdminBundle:Course:data.html.twig', array(
            'courses'   => $courses,
            'paginator' => $paginator
        ));
    }

    public function lessonDataAction($id)
    {
        $course = $this->getCourseService()->tryManageCourse($id);

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

        return $this->render('TopxiaAdminBundle:Course:lesson-data.html.twig', array(
            'course'  => $course,
            'lessons' => $lessons
        ));
    }

    public function chooserAction(Request $request)
    {
        $conditions             = $request->query->all();
        $conditions["parentId"] = 0;

        if (isset($conditions["categoryId"]) && $conditions["categoryId"] == "") {
            unset($conditions["categoryId"]);
        }

        if (isset($conditions["status"]) && $conditions["status"] == "") {
            unset($conditions["status"]);
        }

        if (isset($conditions["title"]) && $conditions["title"] == "") {
            unset($conditions["title"]);
        }

        if (isset($conditions["creator"]) && $conditions["creator"] == "") {
            unset($conditions["creator"]);
        }

        $count = $this->getCourseService()->searchCourseCount($conditions);

        $paginator = new Paginator($this->get('request'), $count, 20);

        $courses = $this->getCourseService()->searchCourses(
            $conditions,
            null,
            $paginator->getOffsetCount(),
            $paginator->getPerPageCount()
        );

        $categories = $this->getCategoryService()->findCategoriesByIds(ArrayToolkit::column($courses, 'categoryId'));

        $users = $this->getUserService()->findUsersByIds(ArrayToolkit::column($courses, 'userId'));

        return $this->render('TopxiaAdminBundle:Course:course-chooser.html.twig', array(
            'conditions' => $conditions,
            'courses'    => $courses,
            'users'      => $users,
            'categories' => $categories,
            'paginator'  => $paginator
        ));
    }

    protected function getSettingService()
    {
        return $this->getServiceKernel()->createService('System.SettingService');
    }

    protected function renderCourseTr($courseId, $request)
    {
        $fields     = $request->query->all();
        $course     = $this->getCourseService()->getCourse($courseId);
        $default    = $this->getSettingService()->get('default', array());
        $classrooms = array();

        if ($fields['filter'] == 'classroom') {
            $classrooms = $this->getClassroomService()->findClassroomsByCoursesIds(array($course['id']));
            $classrooms = ArrayToolkit::index($classrooms, 'courseId');

            foreach ($classrooms as $key => $classroom) {
                $classroomInfo                      = $this->getClassroomService()->getClassroom($classroom['classroomId']);
                $classrooms[$key]['classroomTitle'] = $classroomInfo['title'];
            }
        }

        return $this->render('TopxiaAdminBundle:Course:tr.html.twig', array(
            'user'       => $this->getUserService()->getUser($course['userId']),
            'category'   => $this->getCategoryService()->getCategory($course['categoryId']),
            'course'     => $course,
            'default'    => $default,
            'classrooms' => $classrooms,
            'filter'     => $fields["filter"]
        ));
    }

    protected function returnDeleteStatus($result, $type)
    {
        $dataDictionary = array('questions' => '??????', 'testpapers' => '??????', 'materials' => '????????????', 'chapters' => '????????????', 'drafts' => '????????????', 'lessons' => '??????', 'lessonLearns' => '????????????', 'lessonReplays' => '????????????', 'lessonViews' => '??????????????????', 'homeworks' => '????????????', 'exercises' => '????????????', 'favorites' => '????????????', 'notes' => '????????????', 'threads' => '????????????', 'reviews' => '????????????', 'announcements' => '????????????', 'statuses' => '????????????', 'members' => '????????????', 'course' => '??????');

        if ($result > 0) {
            $message = $dataDictionary[$type]."????????????";
            return array('success' => true, 'message' => $message);
        } else {
            if ($type == "homeworks" || $type == "exercises") {
                $message = $dataDictionary[$type]."??????????????????????????????????????????????????????";
                return array('success' => false, 'message' => $message);
            } elseif ($type == 'course') {
                $message = $dataDictionary[$type]."????????????";
                return array('success' => false, 'message' => $message);
            } else {
                $message = $dataDictionary[$type]."??????????????????";
                return array('success' => false, 'message' => $message);
            }
        }
    }

    protected function getCourseService()
    {
        return $this->getServiceKernel()->createService('Course.CourseService');
    }

    protected function getCourseDeleteService()
    {
        return $this->getServiceKernel()->createService('Course.CourseDeleteService');
    }

    protected function getCourseCopyService()
    {
        return $this->getServiceKernel()->createService('Course.CourseCopyService');
    }

    protected function getCategoryService()
    {
        return $this->getServiceKernel()->createService('Taxonomy.CategoryService');
    }

    protected function getTestpaperService()
    {
        return $this->getServiceKernel()->createService('Testpaper.TestpaperService');
    }

    protected function getAppService()
    {
        return $this->getServiceKernel()->createService('CloudPlatform.AppService');
    }

    protected function getClassroomService()
    {
        return $this->getServiceKernel()->createService('Classroom:Classroom.ClassroomService');
    }

    protected function getPasswordEncoder()
    {
        return new MessageDigestPasswordEncoder('sha256');
    }
}
