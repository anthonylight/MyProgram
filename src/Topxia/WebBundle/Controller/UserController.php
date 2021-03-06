<?php
namespace Topxia\WebBundle\Controller;

use Topxia\Common\Paginator;
use Topxia\Common\ArrayToolkit;
use Symfony\Component\HttpFoundation\Request;

class UserController extends BaseController
{
    public function headerBlockAction($user)
    {
        $userProfile = $this->getUserService()->getUserProfile($user['id']);
        $user        = array_merge($user, $userProfile);

        if ($this->getCurrentUser()->isLogin()) {
            $isFollowed = $this->getUserService()->isFollowed($this->getCurrentUser()->id, $user['id']);
        } else {
            $isFollowed = false;
        }
        return $this->render('TopxiaWebBundle:User:header-link.html.twig', array(
            'user'       => $user,
            'isFollowed' => $isFollowed
        ));


//        return $this->render('TopxiaWebBundle:User:header-block.html.twig', array(
//            'user'       => $user,
//            'isFollowed' => $isFollowed
//        ));
    }

    public function showAction(Request $request, $id)
    {
        $user                 = $this->tryGetUser($id);
        $userProfile          = $this->getUserService()->getUserProfile($user['id']);
        $userProfile['about'] = strip_tags($userProfile['about'], '');
        $userProfile['about'] = preg_replace("/ /", "", $userProfile['about']);
        $user                 = array_merge($user, $userProfile);
        $user['nav']='index';
        //个人作品
        $threads=$this->getGroupThreadService()->searchThreads(
            array('userId' => $user['id']),
            array(array('createdTime','DESC')),
            0,
            6
        );
        foreach($threads as  $key => $perGroup ){
            $soContent = $perGroup['content'];
            $soImages = '~<img [^>]* />~';
            preg_match_all( $soImages, $soContent, $thePics );
            $liked = $this->getGroupThreadService()->isliked($user['id'],$perGroup['id']);
            $threads[$key]['liked'] = $liked;
            if(count($thePics[0])!=0) {
                $allPics = count($thePics[0]);
                preg_match('/<img.+src=\"?(.+\.(jpg|gif|bmp|bnp|png))\"?.+>/i', $thePics[0][0], $match);
                $threads[$key]['thread_pic'] = $match[1];
            } else {
                unset($threads[$key]);
            }
        }
        $user['threads']=$threads;


        if (in_array('ROLE_TEACHER', $user['roles'])) {
            return $this->_teachAction($user);
        }

        return $this->_learnAction($user);
    }

    public function learnAction(Request $request, $type='normal',$id)
    {
        $user                 = $this->tryGetUser($id);
        $userProfile          = $this->getUserService()->getUserProfile($user['id']);
        $userProfile['about'] = strip_tags($userProfile['about'], '');
        $userProfile['about'] = preg_replace("/ /", "", $userProfile['about']);
        $user                 = array_merge($user, $userProfile);
        if($type=='travel'){
            $user['nav']='travel';
        }else{
            $user['nav']='course';
        }
        $user['nav_next']='learn';
        return $this->_learnAction($user,$type);
    }

    public function threadAction(Request $request, $id)
    {
        $user                 = $this->tryGetUser($id);
        $userProfile          = $this->getUserService()->getUserProfile($user['id']);
        $userProfile['about'] = strip_tags($userProfile['about'], '');
        $userProfile['about'] = preg_replace("/ /", "", $userProfile['about']);
        $user                 = array_merge($user, $userProfile);
        $user['nav']='threads';
        $followings    = $this->getUserService()->findAllUserFollowing($user['id']);
        $user['following']=count($followings);

        $followers            = $this->getUserService()->findAllUserFollower($user['id']);
        $user['follower']=count($followers);

        $paginator              = new Paginator(
            $this->get('request'),
            $this->getGroupThreadService()->searchThreadsCount(array('userId' => $user['id'])),
            6
        );



        //个人作品
        $threads=$this->getGroupThreadService()->searchThreads(
            array('userId' => $user['id']),
            array(array('createdTime','DESC')),
            $paginator->getOffsetCount(),
            $paginator->getPerPageCount()
        );
        foreach($threads as  $key => $perGroup ){
            $soContent = $perGroup['content'];
            $soImages = '~<img [^>]* />~';
            preg_match_all( $soImages, $soContent, $thePics );
            $liked = $this->getGroupThreadService()->isliked($user['id'],$perGroup['id']);
            $threads[$key]['liked'] = $liked;
            if(count($thePics[0])!=0) {
                $allPics = count($thePics[0]);
                preg_match('/<img.+src=\"?(.+\.(jpg|gif|bmp|bnp|png))\"?.+>/i', $thePics[0][0], $match);
                $threads[$key]['thread_pic'] = $match[1];
            } else {
                unset($threads[$key]);
            }
        }
        return $this->render('TopxiaWebBundle:User:user.html.twig', array(
            'user'      => $user,
            'threads'   => $threads,
            'type'      => 'threads',
            'paginator' => $paginator
        ));
    }

    public function teachAction(Request $request, $type='normal',$id)
    {
        $user                 = $this->tryGetUser($id);
        $userProfile          = $this->getUserService()->getUserProfile($user['id']);
        $userProfile['about'] = strip_tags($userProfile['about'], '');
        $userProfile['about'] = preg_replace("/ /", "", $userProfile['about']);
        $user                 = array_merge($user, $userProfile);
        if($type=='travel'){
            $user['nav']='travel';
        }else{
            $user['nav']='course';
        }
        $user['nav_next']='teach';
        return $this->_teachAction($user,$type);
    }

    public function learningAction(Request $request, $id)
    {
        $user                 = $this->tryGetUser($id);
        $userProfile          = $this->getUserService()->getUserProfile($user['id']);
        $userProfile['about'] = strip_tags($userProfile['about'], '');
        $userProfile['about'] = preg_replace("/ /", "", $userProfile['about']);
        $user                 = array_merge($user, $userProfile);
        $classrooms           = array();

        $studentClassrooms = $this->getClassroomService()->searchMembers(array('role' => 'student', 'userId' => $user['id']), array('createdTime', 'desc'), 0, 9999);
        $auditorClassrooms = $this->getClassroomService()->searchMembers(array('role' => 'auditor', 'userId' => $user['id']), array('createdTime', 'desc'), 0, 9999);

        $classrooms = array_merge($studentClassrooms, $auditorClassrooms);

        $classroomIds = ArrayToolkit::column($classrooms, 'classroomId');
        $conditions   = array(
            'status'       => 'published',
            'showable'     => '1',
            'classroomIds' => $classroomIds
        );
        $classrooms = $this->getClassroomService()->searchClassrooms($conditions, array('createdTime', 'DESC'), 0, count($classroomIds));

        foreach ($classrooms as $key => $classroom) {
            if (empty($classroom['teacherIds'])) {
                $classroomTeacherIds = array();
            } else {
                $classroomTeacherIds = $classroom['teacherIds'];
            }

            $teachers                     = $this->getUserService()->findUsersByIds($classroomTeacherIds);
            $classrooms[$key]['teachers'] = $teachers;
        }

        $members = $this->getClassroomService()->findMembersByUserIdAndClassroomIds($user['id'], $classroomIds);

        return $this->render("TopxiaWebBundle:User:classroom-learning.html.twig", array(
            'classrooms' => $classrooms,
            'members'    => $members,
            'user'       => $user
        ));
    }

    public function teachingAction(Request $request, $id)
    {
        $user                 = $this->tryGetUser($id);
        $userProfile          = $this->getUserService()->getUserProfile($user['id']);
        $userProfile['about'] = strip_tags($userProfile['about'], '');
        $userProfile['about'] = preg_replace("/ /", "", $userProfile['about']);
        $user                 = array_merge($user, $userProfile);
        $conditions           = array(
            'roles'  => array('teacher', 'headTeacher'),
            'userId' => $user['id']
        );
        $classroomMembers = $this->getClassroomService()->searchMembers($conditions, array('createdTime', 'desc'), 0, 9999);

        $classroomIds = ArrayToolkit::column($classroomMembers, 'classroomId');
        $conditions   = array(
            'status'       => 'published',
            'showable'     => '1',
            'classroomIds' => $classroomIds
        );

        $classrooms = $this->getClassroomService()->searchClassrooms($conditions, array('createdTime', 'DESC'), 0, count($classroomIds));

        $members = $this->getClassroomService()->findMembersByUserIdAndClassroomIds($user['id'], $classroomIds);

        foreach ($classrooms as $key => $classroom) {
            if (empty($classroom['teacherIds'])) {
                $classroomTeacherIds = array();
            } else {
                $classroomTeacherIds = $classroom['teacherIds'];
            }

            $teachers                     = $this->getUserService()->findUsersByIds($classroomTeacherIds);
            $classrooms[$key]['teachers'] = $teachers;
        }


        return $this->render('TopxiaWebBundle:User:classroom-teaching.html.twig', array(
            'classrooms' => $classrooms,
            'members'    => $members,
            'user'       => $user
        ));
    }

    public function favoritedAction(Request $request, $type='normal',$id)
    {
        $user                 = $this->tryGetUser($id);
        $userProfile          = $this->getUserService()->getUserProfile($user['id']);
        $userProfile['about'] = strip_tags($userProfile['about'], '');
        $userProfile['about'] = preg_replace("/ /", "", $userProfile['about']);
        $user                 = array_merge($user, $userProfile);
        $paginator            = new Paginator(
            $this->get('request'),
            $this->getCourseService()->findUserFavoritedCourseCount($user['id']),
            10
        );

        $courses = $this->getCourseService()->findUserFavoritedCourses(
            $user['id'],
            $paginator->getOffsetCount(),
            $paginator->getPerPageCount()
        );

        //过滤课程
        foreach ($courses as $kk=>$vv){
            if($vv['type']!=$type){
                unset($courses[$kk]);
            }
        }

        if($type=='travel'){
            $user['nav']='travel';
        }else{
            $user['nav']='course';
        }
        $user['nav_next']='favorited';
        $followings    = $this->getUserService()->findAllUserFollowing($user['id']);
        $user['following']=count($followings);

        $followers            = $this->getUserService()->findAllUserFollower($user['id']);
        $user['follower']=count($followers);

        return $this->render('TopxiaWebBundle:User:user.html.twig', array(
            'user'      => $user,
            'courses'   => $courses,
            'paginator' => $paginator,
            'type'      => 'favorited'
        ));

//        return $this->render('TopxiaWebBundle:User:courses.html.twig', array(
//            'user'      => $user,
//            'courses'   => $courses,
//            'paginator' => $paginator,
//            'type'      => 'favorited'
//        ));
    }

    public function groupAction(Request $request, $id)
    {
        $user                 = $this->tryGetUser($id);
        $userProfile          = $this->getUserService()->getUserProfile($user['id']);
        $userProfile['about'] = strip_tags($userProfile['about'], '');
        $userProfile['about'] = preg_replace("/ /", "", $userProfile['about']);
        $user                 = array_merge($user, $userProfile);
        $admins               = $this->getGroupService()->searchMembers(array('userId' => $user['id'], 'role' => 'admin'),
            array('createdTime', "DESC"), 0, 1000
        );
        $owners = $this->getGroupService()->searchMembers(array('userId' => $user['id'], 'role' => 'owner'),
            array('createdTime', "DESC"), 0, 1000
        );
        $members     = array_merge($admins, $owners);
        $groupIds    = ArrayToolkit::column($members, 'groupId');
        $adminGroups = $this->getGroupService()->getGroupsByids($groupIds);

        $paginator = new Paginator(
            $this->get('request'),
            $this->getGroupService()->searchMembersCount(array('userId' => $user['id'], 'role' => 'member')),
            12
        );

        $members = $this->getGroupService()->searchMembers(array('userId' => $user['id'], 'role' => 'member'), array('createdTime', "DESC"), $paginator->getOffsetCount(),
            $paginator->getPerPageCount());

        $groupIds = ArrayToolkit::column($members, 'groupId');
        $groups   = $this->getGroupService()->getGroupsByids($groupIds);

        return $this->render('TopxiaWebBundle:User:group.html.twig', array(
            'user'        => $user,
            'type'        => 'group',
            'adminGroups' => $adminGroups,
            'paginator'   => $paginator,
            'groups'      => $groups
        ));
    }

    public function followingAction(Request $request, $id)
    {
        $user                 = $this->tryGetUser($id);
        $userProfile          = $this->getUserService()->getUserProfile($user['id']);
        $userProfile['about'] = strip_tags($userProfile['about'], '');
        $userProfile['about'] = preg_replace("/ /", "", $userProfile['about']);
        $user                 = array_merge($user, $userProfile);
        $followings           = $this->getUserService()->findAllUserFollowing($user['id']);
        $user['nav']='following';
        $user['nav_next']='following';
        $user['following']=count($followings);

        $followers            = $this->getUserService()->findAllUserFollower($user['id']);
        $user['follower']=count($followers);
        return $this->render('TopxiaWebBundle:User:user.html.twig', array(
            'user'      => $user,
            'friends'   => $followings,
            'friendNav' => 'following',
            'type'      => 'friend',
        ));

//        return $this->render('TopxiaWebBundle:User:friend.html.twig', array(
//            'user'      => $user,
//            'friends'   => $followings,
//            'friendNav' => 'following'
//        ));
    }

    public function followerAction(Request $request, $id)
    {
        $user                 = $this->tryGetUser($id);
        $userProfile          = $this->getUserService()->getUserProfile($user['id']);
        $userProfile['about'] = strip_tags($userProfile['about'], '');
        $userProfile['about'] = preg_replace("/ /", "", $userProfile['about']);
        $user                 = array_merge($user, $userProfile);
        $followers            = $this->getUserService()->findAllUserFollower($user['id']);

        $user['nav']='following';
        $user['nav_next']='follower';
        $followings    = $this->getUserService()->findAllUserFollowing($user['id']);
        $user['following']=count($followings);
        $user['follower']=count($followers);

        return $this->render('TopxiaWebBundle:User:user.html.twig', array(
            'user'      => $user,
            'friends'   => $followers,
            'friendNav' => 'follower',
            'type'      => 'friend',
        ));
//        return $this->render('TopxiaWebBundle:User:friend.html.twig', array(
//            'user'      => $user,
//            'friends'   => $followers,
//            'friendNav' => 'follower'
//        ));
    }

    public function remindCounterAction(Request $request)
    {
        $user    = $this->getCurrentUser();
        $counter = array('newMessageNum' => 0, 'newNotificationNum' => 0);

        if ($user->isLogin()) {
            $counter['newMessageNum']      = $user['newMessageNum'];
            $counter['newNotificationNum'] = $user['newNotificationNum'];
        }

        return $this->createJsonResponse($counter);
    }

    public function unfollowAction(Request $request, $id)
    {
        $user = $this->getCurrentUser();

        if (!$user->isLogin()) {
            throw $this->createAccessDeniedException();
        }

        $this->getUserService()->unFollow($user['id'], $id);

        return $this->createJsonResponse(true);
    }

    public function followAction(Request $request, $id)
    {
        $user = $this->getCurrentUser();

        if (!$user->isLogin()) {
            throw $this->createAccessDeniedException();
        }

        $this->getUserService()->follow($user['id'], $id);

        return $this->createJsonResponse(true);
    }

    public function checkPasswordAction(Request $request)
    {
        $password    = $request->query->get('value');
        $currentUser = $this->getCurrentUser();

        if (!$currentUser->isLogin()) {
            $response = array('success' => false, 'message' => '请先登入');
        }

        if (!$this->getUserService()->verifyPassword($currentUser['id'], $password)) {
            $response = array('success' => false, 'message' => '输入的密码不正确');
        } else {
            $response = array('success' => true, 'message' => '');
        }

        return $this->createJsonResponse($response);
    }

    public function cardShowAction(Request $request, $userId)
    {
        $user        = $this->tryGetUser($userId);
        $currentUser = $this->getCurrentUser();
        $profile     = $this->getUserService()->getUserProfile($userId);
        $isFollowed  = false;

        if ($currentUser->isLogin()) {
            $isFollowed = $this->getUserService()->isFollowed($currentUser['id'], $userId);
        }

        $user['learningNum']  = $this->getCourseService()->findUserLearnCourseCount($userId);
        $user['followingNum'] = $this->getUserService()->findUserFollowingCount($userId);
        $user['followerNum']  = $this->getUserService()->findUserFollowerCount($userId);
        $levels               = array();

        if ($this->isPluginInstalled('vip')) {
            $levels = ArrayToolkit::index($this->getLevelService()->searchLevels(array('enabled' => 1), 0, 100), 'id');
        }

        return $this->render('TopxiaWebBundle:User:card-show.html.twig', array(
            'user'       => $user,
            'profile'    => $profile,
            'isFollowed' => $isFollowed,
            'levels'     => $levels,
            'nowTime'    => time()
        ));
    }

    public function fillUserInfoAction(Request $request)
    {
        $auth = $this->getSettingService()->get('auth');
        $user = $this->getCurrentUser();

        if ($auth['fill_userinfo_after_login'] && !isset($auth['registerSort'])) {
            return $this->redirect($this->generateUrl('homepage'));
        }

        if (!$user->isLogin()) {
            return $this->createMessageResponse('error', '请先登录！');
        }

        $goto = $this->getTargetPath($request);

        if ($request->getMethod() == 'POST') {
            $formData = $request->request->all();

            $userInfo = ArrayToolkit::parts($formData, array(
                'truename',
                'mobile',
                'qq',
                'company',
                'weixin',
                'weibo',
                'idcard',
                'gender',
                'job',
                'intField1', 'intField2', 'intField3', 'intField4', 'intField5',
                'floatField1', 'floatField2', 'floatField3', 'floatField4', 'floatField5',
                'dateField1', 'dateField2', 'dateField3', 'dateField4', 'dateField5',
                'varcharField1', 'varcharField2', 'varcharField3', 'varcharField4', 'varcharField5', 'varcharField10', 'varcharField6', 'varcharField7', 'varcharField8', 'varcharField9',
                'textField1', 'textField2', 'textField3', 'textField4', 'textField5', 'textField6', 'textField7', 'textField8', 'textField9', 'textField10'
            ));

            if (isset($formData['email']) && !empty($formData['email'])) {
                $this->getAuthService()->changeEmail($user['id'], null, $formData['email']);
                $this->authenticateUser($this->getUserService()->getUser($user['id']));

                if (!$user['setup']) {
                    $this->getUserService()->setupAccount($user['id']);
                }
            }

            $userInfo = $this->getUserService()->updateUserProfile($user['id'], $userInfo);

            return $this->redirect($goto);
        }

        $userFields = $this->getUserFieldService()->getAllFieldsOrderBySeqAndEnabled();
        $userFields = ArrayToolkit::index($userFields, 'fieldName');
        $userInfo   = $this->getUserService()->getUserProfile($user['id']);

        return $this->render('TopxiaWebBundle:User:fill-userinfo-fields.html.twig', array(
            'userFields' => $userFields,
            'user'       => $userInfo,
            'goto'       => $goto
        ));
    }

    protected function getUserService()
    {
        return $this->getServiceKernel()->createService('User.UserService');
    }

    protected function getCourseService()
    {
        return $this->getServiceKernel()->createService('Course.CourseService');
    }

    protected function getThreadService()
    {
        return $this->getServiceKernel()->createService('Course.ThreadService');
    }

    protected function getGroupThreadService()
    {
        return $this->getServiceKernel()->createService('Group.ThreadService');
    }

    protected function getNoteService()
    {
        return $this->getServiceKernel()->createService('Course.NoteService');
    }

    protected function getNotificationService()
    {
        return $this->getServiceKernel()->createService('User.NotificationService');
    }

    protected function tryGetUser($id)
    {
        $user = $this->getUserService()->getUser($id);

        if (empty($user)) {
            throw $this->createNotFoundException();
        }

        return $user;
    }

    protected function _learnAction($user,$type='normal')
    {
        $paginator = new Paginator(
            $this->get('request'),
            $this->getCourseService()->findUserLearnCourseCountNotInClassroom($user['id']),
            12
        );

        $courses = $this->getCourseService()->findUserLearnCoursesNotInClassroom(
            $user['id'],
            $paginator->getOffsetCount(),
            $paginator->getPerPageCount(),
            true,
            $type
        );

        $followings    = $this->getUserService()->findAllUserFollowing($user['id']);
        $user['following']=count($followings);

        $followers            = $this->getUserService()->findAllUserFollower($user['id']);
        $user['follower']=count($followers);

        $userIds = array();
        foreach ($courses as $course) {
            $userIds = array_merge($userIds, $course['teacherIds']);
        }
        $Teachers   = $this->getUserService()->findUsersByIds($userIds);

        return $this->render('TopxiaWebBundle:User:user.html.twig', array(
            'user'      => $user,
            'courses'   => $courses,
            'paginator' => $paginator,
            'type'      => 'learn',
            'teachers'  => $Teachers
        ));

//        return $this->render('TopxiaWebBundle:User:courses.html.twig', array(
//            'user'      => $user,
//            'courses'   => $courses,
//            'paginator' => $paginator,
//            'type'      => 'learn'
//        ));
    }

    protected function _teachAction($user,$type='normal')
    {
        $conditions = array(
            'userId'   => $user['id'],
            'parentId' => 0,
        );

        $paginator = new Paginator(
            $this->get('request'),
            $this->getCourseService()->findUserTeachCourseCount($conditions,true,$type),
            10
        );




        $courses = $this->getCourseService()->findUserTeachCourses(
            $conditions,
            $paginator->getOffsetCount(),
            $paginator->getPerPageCount(),
            true,
            $type
        );



        $followings    = $this->getUserService()->findAllUserFollowing($user['id']);
        $user['following']=count($followings);

        $followers            = $this->getUserService()->findAllUserFollower($user['id']);
        $user['follower']=count($followers);

        return $this->render('TopxiaWebBundle:User:user.html.twig', array(
            'user'      => $user,
            'courses'   => $courses,
            'paginator' => $paginator,
            'type'      => 'index'
        ));


//        return $this->render('TopxiaWebBundle:User:courses.html.twig', array(
//            'user'      => $user,
//            'courses'   => $courses,
//            'paginator' => $paginator,
//            'type'      => 'teach'
//        ));
    }

    protected function getGroupService()
    {
        return $this->getServiceKernel()->createService('Group.GroupService');
    }

    protected function getClassroomService()
    {
        return $this->getServiceKernel()->createService('Classroom:Classroom.ClassroomService');
    }

    protected function getSettingService()
    {
        return $this->getServiceKernel()->createService('System.SettingService');
    }

    protected function getUserFieldService()
    {
        return $this->getServiceKernel()->createService('User.UserFieldService');
    }

    protected function getAuthService()
    {
        return $this->getServiceKernel()->createService('User.AuthService');
    }

    protected function getLevelService()
    {
        return $this->getServiceKernel()->createService('Vip:Vip.LevelService');
    }
}
