<?php
namespace Topxia\AdminBundle\Controller;

use Topxia\Common\Paginator;
use Symfony\Component\HttpFoundation\Request;
use Topxia\AdminBundle\Controller\BaseController;
use Topxia\Common\ArrayToolkit;

class TagController extends BaseController
{
    public function indexAction(Request $request)
    {
        $total     = $this->getTagService()->searchTagCount($conditions = array());
        $paginator = new Paginator($request, $total, 20);
        $tags      = $this->getTagService()->searchTags($conditions = array(), $paginator->getOffsetCount(), $paginator->getPerPageCount());

        foreach ($tags as &$tag) {
            $tagGroups = $this->getTagService()->findTagGroupsByTagId($tag['id']);

            $groupNames = ArrayToolkit::column($tagGroups, 'name');
            if (!empty($groupNames)) {
                $tag['groupNames'] = $groupNames;
            } else {
                $tag['groupNames'] = array();
            }
        }

        return $this->render('TopxiaAdminBundle:Tag:index.html.twig', array(
            'tags'         => $tags,
            'paginator'    => $paginator,
        ));
    }

    public function createAction(Request $request)
    {
        if ('POST' == $request->getMethod()) {
            $tag = $this->getTagService()->addTag($request->request->all());

            $tagRelation = $this->getTagService()->findTagRelationsByTagIds(array($tag['id']));

            return $this->render('TopxiaAdminBundle:Tag:list-tr.html.twig', array(
                'tag'          => $tag,
                'tagRelations' => $tagRelation,
            ));
        }

        return $this->render('TopxiaAdminBundle:Tag:tag-modal.html.twig', array(
            'tag' => array('id' => 0, 'name' => '')
        ));
    }

    public function updateAction(Request $request, $id)
    {
        $tag = $this->getTagService()->getTag($id);

        if (empty($tag)) {
            throw $this->createNotFoundException();
        }

        if ('POST' == $request->getMethod()) {
            $tag = $this->getTagService()->updateTag($id, $request->request->all());
            return $this->render('TopxiaAdminBundle:Tag:list-tr.html.twig', array(
                'tag' => $tag
            ));
        }

        return $this->render('TopxiaAdminBundle:Tag:tag-modal.html.twig', array(
            'tag' => $tag
        ));
    }

    public function deleteAction(Request $request, $id)
    {
        $this->getTagService()->deleteTag($id);

        return $this->createJsonResponse(true);
    }

    public function checkNameAction(Request $request)
    {
        $name    = $request->query->get('value');
        $exclude = $request->query->get('exclude');

        $avaliable = $this->getTagService()->isTagNameAvalieable($name, $exclude);

        if ($avaliable) {
            $response = array('success' => true, 'message' => '');
        } else {
            $response = array('success' => false, 'message' => $this->getServiceKernel()->trans('???????????????'));
        }

        return $this->createJsonResponse($response);
    }

    protected function getTagService()
    {
        return $this->getServiceKernel()->createService('Taxonomy.TagService');
    }

    protected function getTagWithException($tagId)
    {
        $tag = $this->getTagService()->getTag($tagId);

        if (empty($tag)) {
            throw $this->createNotFoundException($this->getServiceKernel()->trans('???????????????!'));
        }

        return $tag;
    }
}
