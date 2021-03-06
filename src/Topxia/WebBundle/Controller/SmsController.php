<?php

namespace Topxia\WebBundle\Controller;

use Topxia\Common\CurlToolkit;
use Topxia\Common\ArrayToolkit;
use Topxia\Common\StringToolkit;
use Symfony\Component\HttpFoundation\Request;
use Topxia\WebBundle\Controller\BaseController;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;

class SmsController extends BaseController
{
    public function prepareAction(Request $request, $targetType, $id)
    {
        $item                  = array();
        $verifiedMobileUserNum = 0;
        $url                   = '';
        $smsType               = 'sms_'.$targetType.'_publish';

        if ($targetType == 'classroom') {
            $item                  = $this->getClassroomService()->getClassroom($id);
            $verifiedMobileUserNum = $this->getUserService()->searchUserCount(array('hasVerifiedMobile' => true, 'locked' => 0));
            $url                   = $this->generateUrl('classroom_show', array('id' => $id));
        } elseif ($targetType == 'course') {
            $item = $this->getCourseService()->getCourse($id);
            $url  = $this->generateUrl('course_show', array('id' => $id));

            if ($item['parentId']) {
                $classroom = $this->getClassroomService()->findClassroomByCourseId($item['id']);

                if ($classroom) {
                    $verifiedMobileUserNum = $this->getClassroomService()->findMobileVerifiedMemberCountByClassroomId($classroom['classroomId'], 1);
                }
            } else {
                $verifiedMobileUserNum = $this->getUserService()->searchUserCount(array('hasVerifiedMobile' => true, 'locked' => 0));
            }
        }

        $item['title'] = StringToolkit::cutter($item['title'], 20, 15, 4);
        return $this->render('TopxiaWebBundle:Sms:sms-send.html.twig', array(
            'item'       => $item,
            'targetType' => $targetType,
            'url'        => $url,
            'count'      => $verifiedMobileUserNum,
            'index'      => 1,
            'isOpen'     => $this->getSmsService()->isOpen($smsType)
        ));
    }

    public function sendAction(Request $request, $targetType, $id)
    {
        $smsType     = 'sms_'.$targetType.'_publish';
        $index       = $request->query->get('index');
        $onceSendNum = 1000;
        $url         = $request->query->get('url');
        $count       = $request->query->get('count');
        $parameters  = array();

        if ($targetType == 'classroom') {
            $classroom                     = $this->getClassroomService()->getClassroom($id);
            $classroomSetting              = $this->getSettingService()->get("classroom");
            $classroomName                 = isset($classroomSetting['name']) ? $classroomSetting['name'] : '??????';
            $classroom['title']            = StringToolkit::cutter($classroom['title'], 20, 15, 4);
            $parameters['classroom_title'] = $classroomName.'??????'.$classroom['title'].'???';
            $description                   = $parameters['classroom_title'].'??????';
            $students                      = $this->getUserService()->searchUsers(array('hasVerifiedMobile' => true), array('createdTime', 'DESC'), $index, $onceSendNum);
        } elseif ($targetType == 'course') {
            $course                     = $this->getCourseService()->getCourse($id);
            $course['title']            = StringToolkit::cutter($course['title'], 20, 15, 4);
            $parameters['course_title'] = '????????????'.$course['title'].'???';
            $description                = $parameters['course_title'].'??????';

            if ($course['parentId']) {
                $classroom = $this->getClassroomService()->findClassroomByCourseId($course['id']);

                if ($classroom) {
                    $count    = $this->getClassroomService()->searchMemberCount(array('classroomId' => $classroom['classroomId']));
                    $students = $this->getClassroomService()->searchMembers(array('classroomId' => $classroom['classroomId']), array('createdTime', 'Desc'), $index, $onceSendNum);
                }
            } else {
                $students = $this->getUserService()->searchUsers(array('hasVerifiedMobile' => true), array('createdTime', 'DESC'), $index, $onceSendNum);
            }
        }

        if (!$this->getSmsService()->isOpen($smsType)) {
            throw new \RuntimeException("????????????????????????!");
        }

        $parameters['url'] = $url.' ';

        if (!empty($students)) {
            if ($targetType == 'course' && $course['parentId']) {
                $studentIds = ArrayToolkit::column($students, 'userId');
            } else {
                $studentIds = ArrayToolkit::column($students, 'id');
            }

            $users = $this->getUserService()->findUsersByIds($studentIds);

            foreach ($users as $key => $value) {
                if (strlen($value['verifiedMobile']) == 0 || $value['locked']) {
                    unset($users[$key]);
                }
            }

            if (!empty($users)) {
                $userIds = ArrayToolkit::column($users, 'id');
                $result  = $this->getSmsService()->smsSend($smsType, $userIds, $description, $parameters);
            }
        }

        if ($count > $index + $onceSendNum) {
            return $this->createJsonResponse(array('index' => $index + $onceSendNum, 'process' => intval(($index + $onceSendNum) / $count * 100)));
        } else {
            return $this->createJsonResponse(array('status' => 'success', 'process' => 100));
        }
    }

    public function changeLinkAction(Request $request)
    {
        $url = $request->getHost();
        $url .= $request->query->get('url');
        $arrResponse = CurlToolkit::request('POST', "http://dwz.cn/create.php", array('url' => $url));

        if ($arrResponse['status'] != 0) {
            $qqArrResponse = CurlToolkit::request('POST', "http://qqurl.com/create/", array('url' => $url));

            if ($qqArrResponse['status'] != 0) {
                return $this->createJsonResponse(array('url' => $url.' '));
            } else {
                return $this->createJsonResponse(array('url' => $qqArrResponse['short_url'].' '));
            }
        } else {
            return $this->createJsonResponse(array('url' => $arrResponse['tinyurl'].' '));
        }
    }

    protected function getSettingService()
    {
        return $this->getServiceKernel()->createService('System.SettingService');
    }

    protected function getSmsService()
    {
        return $this->getServiceKernel()->createService('Sms.SmsService');
    }

    protected function getCourseService()
    {
        return $this->getServiceKernel()->createService('Course.CourseService');
    }

    protected function getClassroomService()
    {
        return $this->getServiceKernel()->createService('Classroom:Classroom.ClassroomService');
    }
}
