<?php

namespace Classroom\Service\Classroom\Impl;

use Topxia\Common\ArrayToolkit;
use Topxia\Service\Common\BaseService;
use Topxia\Service\Common\ServiceEvent;
use Classroom\Service\Classroom\ClassroomReviewService;

class ClassroomReviewServiceImpl extends BaseService implements ClassroomReviewService
{
    public function getReview($id)
    {
        return $this->getClassroomReviewDao()->getReview($id);
    }

    public function searchReviews($conditions, $orderBy, $start, $limit)
    {
        $conditions = $this->_prepareReviewSearchConditions($conditions);

        return $this->getClassroomReviewDao()->searchReviews($conditions, $orderBy, $start, $limit);
    }

    public function searchReviewCount($conditions)
    {
        $conditions = $this->_prepareReviewSearchConditions($conditions);
        $count      = $this->getClassroomReviewDao()->searchReviewCount($conditions);

        return $count;
    }

    public function getUserClassroomReview($userId, $classroomId)
    {
        $user = $this->getUserService()->getUser($userId);

        $classroom = $this->getClassroomDao()->getClassroom($classroomId);

        if (empty($classroom)) {
            throw $this->createServiceException("Classroom is not Exist!");
        }

        return $this->getClassroomReviewDao()->getReviewByUserIdAndClassroomId($userId, $classroomId);
    }

    private function _prepareReviewSearchConditions($conditions)
    {
        $conditions = array_filter($conditions, function ($value) {
            if (is_array($value) || ctype_digit((string) $value)) {
                return true;
            }

            return !empty($value);
        }

        );

        if (isset($conditions['author'])) {
            $author               = $this->getUserService()->getUserByNickname($conditions['author']);
            $conditions['userId'] = $author ? $author['id'] : -1;
        }

        return $conditions;
    }

    public function saveReview($fields)
    {
        if (!ArrayToolkit::requireds($fields, array('classroomId', 'userId', 'rating'))) {
            throw $this->createServiceException($this->getKernel()->trans('?????????????????????????????????'));
        }

        if ($fields['rating'] > 5) {
            throw $this->createServiceException($this->getKernel()->trans('?????????????????????????????????'));
        }

        $this->getClassroomService()->tryTakeClassroom($fields['classroomId']);

        $classroom = $this->getClassroomDao()->getClassroom($fields['classroomId']);

        $userId = $this->getCurrentUser()->id;

        if (empty($classroom)) {
            throw $this->createServiceException($this->getKernel()->trans('??????(#%classroomId%)???????????????????????????', array('%classroomId%' => $fields['classroomId'])));
        }

        $user = $this->getUserService()->getUser($fields['userId']);

        if (empty($user)) {
            throw $this->createServiceException($this->getKernel()->trans('??????(#%userId%)?????????,????????????!', array('%userId%' => $fields['userId'])));
        }

        $review = $this->getClassroomReviewDao()->getReviewByUserIdAndClassroomId($user['id'], $classroom['id']);

        $fields['parentId'] = empty($fields['parentId']) ? 0 : $fields['parentId'];
        if (empty($review) || ($review && $fields['parentId'] > 0)) {
            $review = $this->getClassroomReviewDao()->addReview(array(
                'userId'      => $fields['userId'],
                'classroomId' => $fields['classroomId'],
                'rating'      => $fields['rating'],
                'content'     => empty($fields['content']) ? '' : $this->purifyHtml($fields['content']),
                'title'       => empty($fields['title']) ? '' : $fields['title'],
                'parentId'    => $fields['parentId'],
                'createdTime' => time(),
                'meta'        => array()
            ));
            $this->dispatchEvent('classReview.add', new ServiceEvent($review));
        } else {
            $review = $this->getClassroomReviewDao()->updateReview($review['id'], array(
                'rating'      => $fields['rating'],
                'title'       => empty($fields['title']) ? '' : $fields['title'],
                'content'     => empty($fields['content']) ? '' : $this->purifyHtml($fields['content']),
                'updatedTime' => time(),
                'meta'        => array()
            ));
        }

        $this->calculateClassroomRating($classroom['id']);

        return $review;
    }

    private function calculateClassroomRating($classroomId)
    {
        $ratingSum = $this->getClassroomReviewDao()->getReviewRatingSumByClassroomId($classroomId);
        $ratingNum = $this->getClassroomReviewDao()->getReviewCountByClassroomId($classroomId);

        $this->getClassroomService()->updateClassroom($classroomId, array(
            'rating'    => $ratingNum ? $ratingSum / $ratingNum : 0,
            'ratingNum' => $ratingNum
        ));
    }

    public function deleteReview($id)
    {
        $user = $this->getCurrentUser();
        if (!$user->isLogin()) {
            throw $this->createAccessDeniedException('not login');
        }

        $review = $this->getReview($id);

        if (empty($review)) {
            throw $this->createAccessDeniedException($this->getKernel()->trans('??????(#%id%)???????????????????????????', array('%id%' => $id)));
        }

        if (!$user->isAdmin() && $review['userId'] != $user['id']) {
            throw $this->createAccessDeniedException('review is not exsits.');
        }

        $this->getClassroomReviewDao()->deleteReview($id);

        $this->calculateClassroomRating($review['classroomId']);

        $this->getLogService()->info('classroom_review', 'delete', "????????????#{$id}");
    }

    protected function getClassroomReviewDao()
    {
        return $this->createDao('Classroom:Classroom.ClassroomReviewDao');
    }

    private function getClassroomService()
    {
        return $this->createService('Classroom:Classroom.ClassroomService');
    }

    protected function getClassroomDao()
    {
        return $this->createDao('Classroom:Classroom.ClassroomDao');
    }

    private function getUserService()
    {
        return $this->createService('User.UserService');
    }

    private function getLogService()
    {
        return $this->createService('System.LogService');
    }
}
