<?php
namespace Topxia\AdminBundle\Controller;

use Topxia\Common\Paginator;
use Topxia\Common\FileToolkit;
use Topxia\Common\ArrayToolkit;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class OrderController extends BaseController
{
    public function indexAction(Request $request)
    {
        return $this->render('TopxiaAdminBundle:Order:index.html.twig', array());
    }

    public function manageAction(Request $request, $targetType)
    {
        $conditions               = $request->query->all();
        $conditions['targetType'] = $targetType;

        if (isset($conditions['keywordType'])) {
            $conditions[$conditions['keywordType']] = trim($conditions['keyword']);
        }

        if (!empty($conditions['startDateTime']) && !empty($conditions['endDateTime'])) {
            $conditions['startTime'] = strtotime($conditions['startDateTime']);
            $conditions['endTime']   = strtotime($conditions['endDateTime']);
        }

        $paginator = new Paginator(
            $request,
            $this->getOrderService()->searchOrderCount($conditions),
            20
        );
        $orders = $this->getOrderService()->searchOrders(
            $conditions,
            array('createdTime', 'DESC'),
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

        return $this->render('TopxiaAdminBundle:Order:manage.html.twig', array(
            'request'    => $request,
            'targetType' => $targetType,
            'orders'     => $orders,
            'users'      => $users,
            'paginator'  => $paginator
        ));
    }

    public function detailAction(Request $request, $id)
    {
        return $this->forward('TopxiaWebBundle:Order:detail', array(
            'id' => $id
        ));
    }

    public function cancelRefundAction(Request $request, $id)
    {
        $this->getClassroomOrderService()->cancelRefundOrder($id);
        return $this->createJsonResponse(true);
    }

    public function auditRefundAction(Request $request, $id)
    {
        $order = $this->getOrderService()->getOrder($id);

        if ($request->getMethod() == 'POST') {
            $data = $request->request->all();

            $pass = $data['result'] == 'pass' ? true : false;
            $this->getOrderService()->auditRefundOrder($order['id'], $pass, $data['amount'], $data['note']);

            if ($pass) {
                if ($this->getClassroomService()->isClassroomStudent($order['targetId'], $order['userId'])) {
                    $this->getClassroomService()->removeStudent($order['targetId'], $order['userId']);
                }
            }

            $this->sendAuditRefundNotification($order, $pass, $data['amount'], $data['note']);

            return $this->createJsonResponse(true);
        }

        return $this->render('TopxiaAdminBundle:CourseOrder:refund-confirm-modal.html.twig', array(
            'order' => $order
        ));
    }

    /**
     *  ????????????
     * @param string $targetType classroom | course | vip
     */
    public function exportCsvAction(Request $request, $targetType)
    {
        $start = $request->query->get('start', 0);

        $magic = $this->setting('magic');
        $limit = $magic['export_limit'];

        $conditions = $this->buildExportCondition($request, $targetType);

        $status = array(
            'created'   => $this->trans('?????????'),
            'paid'      => $this->trans('?????????'),
            'refunding' => $this->trans('?????????'),
            'refunded'  => $this->trans('?????????'),
            'cancelled' => $this->trans('?????????')
        );

        $payment        = $this->get('codeages_plugin.dict_twig_extension')->getDict('payment');
        $orderCount     = $this->getOrderService()->searchOrderCount($conditions);
        $orders         = $this->getOrderService()->searchOrders($conditions, array('createdTime', 'DESC'), $start, $limit);
        $studentUserIds = ArrayToolkit::column($orders, 'userId');

        $users = $this->getUserService()->findUsersByIds($studentUserIds);
        $users = ArrayToolkit::index($users, 'id');

        $profiles = $this->getUserService()->findUserProfilesByIds($studentUserIds);
        $profiles = ArrayToolkit::index($profiles, 'id');

        if ($targetType == 'vip') {
            $str = $this->trans('?????????').','.
            $this->trans('????????????').','.
            $this->trans('????????????').','.
            $this->trans('?????????').','.
            $this->trans('??????').','.
            $this->trans('????????????').','.
            $this->trans('????????????').','.
            $this->trans('????????????').','.
            $this->trans('????????????');
        } else {
            $str = $this->trans('?????????').','.
            $this->trans('????????????').','.
            $this->trans('????????????').','.
            $this->trans('????????????').','.
            $this->trans('?????????').','.
            $this->trans('????????????').','.
            $this->trans('???????????????').','.
            $this->trans('????????????').','.
            $this->trans('????????????').','.
            $this->trans('?????????').','.
            $this->trans('??????').','.
            $this->trans('??????').','.
            $this->trans('????????????').','.
            $this->trans('????????????');
        }

        $str .= "\r\n";

        $results = array();

        if ($targetType == 'vip') {
            $results = $this->generateVipExportData($orders, $status, $users, $profiles, $payment, $results);
        } else {
            $results = $this->generateExportData($orders, $status, $payment, $users, $profiles, $results);
        }

        $loop = $request->query->get('loop', 0);
        ++$loop;

        $enableRedirect = $loop * $limit < $orderCount; //??????????????????????????????????????????,?????????????????????
        $readTempDate   = $start;
        $file           = $request->query->get('fileName', $this->genereateExportCsvFileName($targetType));

        if ($enableRedirect) {
            $content = implode("\r\n", $results);
            file_put_contents($file, $content."\r\n", FILE_APPEND);
            return $this->redirect($this->generateUrl('admin_order_manage_export_csv', array('targetType' => $targetType, 'loop' => $loop, 'start' => $loop * $limit, 'fileName' => $file)));
        } elseif ($readTempDate) {
            $str .= file_get_contents($file);
            FileToolkit::remove($file);
        }

        $str .= implode("\r\n", $results);
        $str      = chr(239).chr(187).chr(191).$str;
        $filename = sprintf("%s-order-(%s).csv", $targetType, date('Y-n-d'));

        $response = new Response();
        $response->headers->set('Content-type', 'text/csv');
        $response->headers->set('Content-Disposition', 'attachment; filename="'.$filename.'"');
        $response->headers->set('Content-length', strlen($str));
        $response->setContent($str);

        return $response;
    }

    private function buildExportCondition($request, $targetType)
    {
        $conditions = $request->query->all();

        if (!empty($conditions['startTime']) && !empty($conditions['endTime'])) {
            $conditions['startTime'] = strtotime($conditions['startTime']);
            $conditions['endTime']   = strtotime($conditions['endTime']);
        }

        $conditions['targetType'] = $targetType;
        return $conditions;
    }

    private function genereateExportCsvFileName($targetType)
    {
        $rootPath = $this->getServiceKernel()->getParameter('topxia.upload.private_directory');
        $user     = $this->getCurrentUser();
        return $rootPath."/export_content".$targetType.$user['id'].time().".txt";
    }

    protected function sendAuditRefundNotification($order, $pass, $amount, $note)
    {
        $course = $this->getClassroomService()->getClassroom($order['targetId']);

        if (empty($course)) {
            return false;
        }

        if ($pass) {
            $message = $this->setting('refund.successNotification', '');
        } else {
            $message = $this->setting('refund.failedNotification', '');
        }

        if (empty($message)) {
            return false;
        }

        $classroomUrl = $this->generateUrl('classroom_show', array('id' => $classroom['id']));
        $variables    = array(
            'classroom' => "<a href='{$classroomUrl}'>{$classroom['title']}</a>",
            'amount'    => $amount,
            'note'      => $note
        );

        $message = StringToolkit::template($message, $variables);
        $this->getNotificationService()->notify($order['userId'], 'default', $message);

        return true;
    }

    protected function getOrderService()
    {
        return $this->getServiceKernel()->createService('Order.OrderService');
    }

    private function generateVipExportData($orders, $status, $users, $profiles, $payment, $results)
    {
        foreach ($orders as $key => $order) {
            $member = "";
            $member .= $order['sn'].",";
            $member .= $status[$order['status']].",";
            $member .= $order['title'].",";
            $member .= $users[$order['userId']]['nickname'].",";
            $member .= $profiles[$order['userId']]['truename'] ? $profiles[$order['userId']]['truename']."," : "-".",";
            $member .= $order['amount'].",";
            $member .= $payment[$order['payment']].",";
            $member .= date('Y-n-d H:i:s', $order['createdTime']).",";

            if ($order['paidTime'] != 0) {
                $member .= date('Y-n-d H:i:s', $order['paidTime']).",";
            } else {
                $member .= "-".",";
            }

            $results[] = $member;
        }

        return $results;
    }

    private function generateExportData($orders, $status, $payment, $users, $profiles, $results)
    {
        foreach ($orders as $key => $order) {
            $member = "";
            $member .= $order['sn'].",";
            $member .= $status[$order['status']].",";
            $member .= $order['title'].",";

            $member .= $order['totalPrice'].",";

            if (!empty($order['coupon'])) {
                $member .= $order['coupon'].",";
            } else {
                $member .= "???".",";
            }

            $member .= $order['couponDiscount'].",";
            $member .= $order['coinRate'] ? ($order['coinAmount'] / $order['coinRate'])."," : '0,';
            $member .= $order['amount'].",";

            $orderPayment = empty($order['payment']) ? 'none' : $order['payment'];
            $member .= $payment[$orderPayment].",";

            $member .= $users[$order['userId']]['nickname'].",";
            $member .= $profiles[$order['userId']]['truename'] ? $profiles[$order['userId']]['truename']."," : "-".",";

            if (preg_match($this->trans('/???????????????/'), $order['title'])) {
                $member .= $this->trans('???????????????,');
            } else {
                $member .= "-,";
            }

            $member .= date('Y-n-d H:i:s', $order['createdTime']).",";

            if ($order['paidTime'] != 0) {
                $member .= date('Y-n-d H:i:s', $order['paidTime']);
            } else {
                $member .= "-";
            }

            $results[] = $member;
        }

        return $results;
    }

    protected function getUserFieldService()
    {
        return $this->getServiceKernel()->createService('User.UserFieldService');
    }

    protected function getCourseService()
    {
        return $this->getServiceKernel()->createService('Course.CourseService');
    }

    protected function getClassroomService()
    {
        return $this->getServiceKernel()->createService('Classroom:Classroom.ClassroomService');
    }

    protected function getCashService()
    {
        return $this->getServiceKernel()->createService('Cash.CashService');
    }

    protected function getCashOrdersService()
    {
        return $this->getServiceKernel()->createService('Cash.CashOrdersService');
    }
}
