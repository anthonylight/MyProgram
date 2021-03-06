<?php

namespace Topxia\AdminBundle\Controller;

use Topxia\Common\Paginator;
use Topxia\Common\FileToolkit;
use Topxia\Common\ArrayToolkit;
use Topxia\Common\BlockToolkit;
use Topxia\Common\StringToolkit;
use Topxia\Service\Common\ServiceException;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class BlockController extends BaseController
{
    public function indexAction(Request $request, $category = '')
    {
        $user = $this->getCurrentUser();

        list($condation, $sort) = $this->dealQueryFields($category);

        $paginator = new Paginator(
            $this->get('request'),
            $this->getBlockService()->searchBlockTemplateCount($condation),
            20
        );
        $blockTemplates = $this->getBlockService()->searchBlockTemplates(
            $condation,
            $sort,
            $paginator->getOffsetCount(),
            $paginator->getPerPageCount()
        );

        $blockTemplateIds = ArrayToolkit::column($blockTemplates, 'id');

        $blocks          = $this->getBlockService()->getBlocksByBlockTemplateIdsAndOrgId($blockTemplateIds, $user['orgId']);
        $blockIds        = ArrayToolkit::column($blocks, 'id');
        $latestHistories = $this->getBlockService()->getLatestBlockHistoriesByBlockIds($blockIds);
        $userIds         = ArrayToolkit::column($latestHistories, 'userId');
        $users           = $this->getUserService()->findUsersByIds($userIds);

        return $this->render('TopxiaAdminBundle:Block:index.html.twig', array(
            'blockTemplates'  => $blockTemplates,
            'users'           => $users,
            'latestHistories' => $latestHistories,
            'paginator'       => $paginator,
            'type'            => $category
        ));
    }

    protected function dealQueryFields($category)
    {
        $sort      = array();
        $condation = array();
        if ($category == 'lastest') {
            $sort = array('updateTime', 'DESC');
        } elseif ($category != 'all') {
            if ($category == 'theme') {
                $theme    = $this->getSettingService()->get('theme', array());
                $category = $theme['uri'];
            }
            $condation['category'] = $category;
        }

        return array($condation, $sort);
    }

    public function blockMatchAction(Request $request)
    {
        $likeString = $request->query->get('q');
        $blocks     = $this->getBlockService()->searchBlockTemplates(array('title' => $likeString), array('updateTime', 'DESC'), 0, 10);

        return $this->createJsonResponse($blocks);
    }

    public function previewAction(Request $request, $id)
    {
        $blockHistory = $this->getBlockService()->getBlockHistory($id);

        return $this->render('TopxiaAdminBundle:Block:blockhistory-preview.html.twig', array(
            'blockHistory' => $blockHistory
        ));
    }

    public function updateAction(Request $request, $blockTemplateId)
    {
        $user = $this->getCurrentUser();

        if ('POST' == $request->getMethod()) {
            $fields           = $request->request->all();
            $fields['userId'] = $user['id'];
            $fields['orgId']  = $user['orgId'];
            if (empty($fields['blockId'])) {
                $block = $this->getBlockService()->createBlock($fields);
            } else {
                $block = $this->getBlockService()->updateBlock($fields['blockId'], $fields);
            }
            $latestBlockHistory = $this->getBlockService()->getLatestBlockHistory();
            $latestUpdateUser   = $this->getUserService()->getUser($latestBlockHistory['userId']);
            $html               = $this->renderView('TopxiaAdminBundle:Block:list-tr.html.twig', array(
                'blockTemplate'    => $block,
                'latestUpdateUser' => $latestUpdateUser,
                'latestHistory'    => $latestBlockHistory
            ));

            return $this->createJsonResponse(array('status' => 'ok', 'html' => $html));
        }

        $block = $this->getBlockService()->getBlockByTemplateIdAndOrgId($blockTemplateId, $user['orgId']);

        return $this->render('TopxiaAdminBundle:Block:block-update-modal.html.twig', array(
            'block' => $block
        ));
    }

    public function blockHistoriesDataAction($blockId)
    {
        $block         = $this->getBlockService()->getBlock($blockId);
        $templateData  = array();
        $templateItems = array();
        $blockHistorys = array();
        $historyUsers  = array();
        $paginator     = new Paginator(
            $this->get('request'),
            null,
            5);
        if (!empty($block['blockId'])) {
            $paginator = new Paginator(
                $this->get('request'),
                $this->getBlockService()->findBlockHistoryCountByBlockId($block['blockId']),
                5
            );

            $blockHistorys = $this->getBlockService()->findBlockHistorysByBlockId(
                $blockId,
                $paginator->getOffsetCount(),
                $paginator->getPerPageCount());

            foreach ($blockHistorys as &$blockHistory) {
                $blockHistory['templateData'] = json_decode($blockHistory['templateData'], true);
            }

            $historyUsers = $this->getUserService()->findUsersByIds(ArrayToolkit::column($blockHistorys, 'userId'));
        }

        return $this->render('TopxiaAdminBundle:Block:block-history-table.html.twig', array(
            'block'         => $block,
            'blockHistorys' => $blockHistorys,
            'historyUsers'  => $historyUsers,
            'paginator'     => $paginator
        ));
    }

    public function editAction(Request $request, $blockTemplateId)
    {
        $block = $this->getBlockService()->getBlockTemplate($blockTemplateId);

        if ('POST' == $request->getMethod()) {
            $fields = $request->request->all();
            $block  = $this->getBlockService()->updateBlockTemplate($block['id'], $fields);
            $user   = $this->getCurrentUser();
            $html   = $this->renderView('TopxiaAdminBundle:Block:list-tr.html.twig', array(
                'blockTemplate' => $block, 'latestUpdateUser' => $user
            ));

            return $this->createJsonResponse(array('status' => 'ok', 'html' => $html));
        }

        return $this->render('TopxiaAdminBundle:Block:block-modal.html.twig', array(
            'editBlock' => $block
        ));
    }

    public function visualEditAction(Request $request, $blockTemplateId)
    {
        $user = $this->getCurrentUser();
        if ('POST' == $request->getMethod()) {
            $condation             = $request->request->all();
            $block['data']         = $condation['data'];
            $block['templateName'] = $condation['templateName'];
            $html                  = BlockToolkit::render($block, $this->container);
            $fields                = array(
                'data'            => $block['data'],
                'content'         => $html,
                'userId'          => $user['id'],
                'blockTemplateId' => $condation['blockTemplateId'],
                'orgId'           => $user['orgId'],
                'code'            => $condation['code'],
                'mode'            => $condation['mode']
            );
            if (empty($condation['blockId'])) {
                $block = $this->getBlockService()->createBlock($fields);
            } else {
                $block = $this->getBlockService()->updateBlock($condation['blockId'], $fields);
            }

            $this->setFlashMessage('success', $this->trans('????????????!'));
        }

        $block = $this->getBlockService()->getBlockByTemplateIdAndOrgId($blockTemplateId, $user['orgId']);

        return $this->render('TopxiaAdminBundle:Block:block-visual-edit.html.twig', array(
            'block'  => $block,
            'action' => 'edit'
        ));
    }

    public function dataViewAction(Request $request, $blockTemplateId)
    {
        $block = $this->getBlockService()->getBlockTemplate($blockTemplateId);
        unset($block['meta']['default']);
        foreach ($block['meta']['items'] as $key => &$item) {
            $item['default'] = $block['data'][$key];
        }

        return new Response('<pre>'.StringToolkit::jsonPettry(StringToolkit::jsonEncode($block['meta'])).'</pre>');
    }

    public function visualHistoryAction(Request $request, $blockTemplateId)
    {
        $user = $this->getCurrentUser();

        $block     = $this->getBlockService()->getBlockByTemplateIdAndOrgId($blockTemplateId, $user['orgId']);
        $paginator = new Paginator(
            $this->get('request'),
            null,
            5
        );
        $blockHistorys = array();
        $historyUsers  = array();

        if (!empty($block)) {
            $paginator = new Paginator(
                $this->get('request'),
                $this->getBlockService()->findBlockHistoryCountByBlockId($block['blockId']),
                20
            );

            $blockHistorys = $this->getBlockService()->findBlockHistorysByBlockId(
                $block['blockId'],
                $paginator->getOffsetCount(),
                $paginator->getPerPageCount());

            $historyUsers = $this->getUserService()->findUsersByIds(ArrayToolkit::column($blockHistorys, 'userId'));
        }

        return $this->render('TopxiaAdminBundle:Block:block-visual-history.html.twig', array(
            'block'         => $block,
            'paginator'     => $paginator,
            'blockHistorys' => $blockHistorys,
            'historyUsers'  => $historyUsers
        ));
    }

    public function createAction(Request $request)
    {
        if ('POST' == $request->getMethod()) {
            $block = $this->getBlockService()->createBlock($request->request->all());
            $user  = $this->getCurrentUser();
            $html  = $this->renderView('TopxiaAdminBundle:Block:list-tr.html.twig', array('blockTemplate' => $block, 'latestUpdateUser' => $user));

            return $this->createJsonResponse(array('status' => 'ok', 'html' => $html));
        }

        $editBlock = array(
            'id'       => 0,
            'title'    => '',
            'code'     => '',
            'mode'     => 'html',
            'template' => ''
        );

        return $this->render('TopxiaAdminBundle:Block:block-modal.html.twig', array(
            'editBlock' => $editBlock
        ));
    }

    public function deleteAction(Request $request, $id)
    {
        try {
            $this->getBlockService()->deleteBlockTemplate($id);

            return $this->createJsonResponse(array('status' => 'ok'));
        } catch (ServiceException $e) {
            return $this->createJsonResponse(array('status' => 'error'));
        }
    }

    public function checkBlockCodeForCreateAction(Request $request)
    {
        $code                = $request->query->get('value');
        $blockTemplateByCode = $this->getBlockService()->getBlockTemplateByCode($code);
        if (empty($blockTemplateByCode)) {
            return $this->createJsonResponse(array('success' => true, 'message' => $this->trans('?????????????????????')));
        }

        return $this->createJsonResponse(array('success' => false, 'message' => $this->trans('??????????????????,???????????????')));
    }

    public function checkBlockTemplateCodeForEditAction(Request $request, $id)
    {
        $code                = $request->query->get('value');
        $blockTemplateByCode = $this->getBlockService()->getBlockTemplateByCode($code);
        if (empty($blockTemplateByCode) || $id == $blockTemplateByCode['id']) {
            return $this->createJsonResponse(array('success' => true, 'message' => 'ok'));
        } elseif ($id != $blockTemplateByCode['id']) {
            return $this->createJsonResponse(array('success' => false, 'message' => $this->trans('?????????????????????????????????????????????')));
        }
    }

    public function uploadAction(Request $request, $blockId)
    {
        $response = array();
        if ($request->getMethod() == 'POST') {
            $file = $request->files->get('file');
            if (!FileToolkit::isImageFile($file)) {
                throw $this->createAccessDeniedException($this->trans('????????????????????????'));
            }

            $filename = 'block_picture_'.time().'.'.$file->getClientOriginalExtension();

            $directory = "{$this->container->getParameter('topxia.upload.public_directory')}/system";
            $file      = $file->move($directory, $filename);

            $block = $this->getBlockService()->getBlock($blockId);

            $url = "{$this->container->getParameter('topxia.upload.public_url_path')}/system/{$filename}";

            $response = array(
                'url' => $url
            );
        }

        return $this->createJsonResponse($response);
    }

    public function picPreviewAction(Request $request, $blockId)
    {
        $url = $request->query->get('url', '');

        return $this->render('TopxiaAdminBundle:Block:picture-preview-modal.html.twig', array(
            'url' => $url
        ));
    }

    public function recoveryAction(Request $request, $blockTemplateId, $historyId)
    {
        $history = $this->getBlockService()->getBlockHistory($historyId);
        $user    = $this->getCurrentUser();
        $block   = $this->getBlockService()->getBlockByTemplateIdAndOrgId($blockTemplateId, $user['orgId']);
        $this->getBlockService()->recovery($block['blockId'], $history);
        $this->setFlashMessage('success', $this->trans('????????????!'));

        return $this->redirect($this->generateUrl('admin_block_visual_edit_history', array('blockTemplateId' => $blockTemplateId)));
    }

    protected function getBlockService()
    {
        return $this->getServiceKernel()->createService('Content.BlockService');
    }

    protected function getSettingService()
    {
        return $this->getServiceKernel()->createService('System.SettingService');
    }
}
