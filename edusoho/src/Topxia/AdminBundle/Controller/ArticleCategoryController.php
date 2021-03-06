<?php
namespace Topxia\AdminBundle\Controller;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;

class ArticleCategoryController extends BaseController
{
    public function indexAction(Request $request)
    {
        $categories = $this->getCategoryService()->getCategoryStructureTree();

        return $this->render('TopxiaAdminBundle:ArticleCategory:index.html.twig', array(
            'categories' => $categories
        ));
    }

    public function createAction(Request $request)
    {
        if ($request->getMethod() == 'POST') {
            $category = $this->getCategoryService()->createCategory($request->request->all());
            return $this->renderTbody();
        }
        $category = array(
            'id'             => 0,
            'name'           => '',
            'code'           => '',
            'parentId'       => (int) $request->query->get('parentId', 0),
            'weight'         => 0,
            'publishArticle' => 1,
            'seoTitle'       => '',
            'seoKeyword'     => '',
            'seoDesc'        => '',
            'published'      => 1
        );

        $categoryTree = $this->getCategoryService()->getCategoryTree();
        return $this->render('TopxiaAdminBundle:ArticleCategory:modal.html.twig', array(
            'category'     => $category,
            'categoryTree' => $categoryTree
        ));
    }

    public function sortAction(Request $request)
    {
        $ids = $request->request->get('ids');

        if (!empty($ids)) {
            $this->getCategoryService()->sortCategories($ids);
        }

        return $this->createJsonResponse(true);
    }

    public function editAction(Request $request, $id)
    {
        $category = $this->getCategoryService()->getCategory($id);
        if (empty($category)) {
            throw $this->createNotFoundException();
        }

        if ($request->getMethod() == 'POST') {
            $category = $this->getCategoryService()->updateCategory($id, $request->request->all());
            return $this->renderTbody();
        }
        $categoryTree = $this->getCategoryService()->getCategoryTree();

        return $this->render('TopxiaAdminBundle:ArticleCategory:modal.html.twig', array(
            'category'     => $category,
            'categoryTree' => $categoryTree
        ));
    }

    public function deleteAction(Request $request, $id)
    {
        $category = $this->getCategoryService()->getCategory($id);
        if (empty($category)) {
            throw $this->createNotFoundException();
        }

        if ($this->canDeleteCategory($id)) {
            return $this->createJsonResponse(array('status' => 'error', 'message' => $this->getServiceKernel()->trans('????????????????????????????????????')));
        } else {
            $this->getCategoryService()->deleteCategory($id);
            return $this->createJsonResponse(array('status' => 'success', 'message' => $this->getServiceKernel()->trans('???????????????')));
        }
    }

    public function canDeleteCategory($id)
    {
        return $this->getCategoryService()->findCategoriesCountByParentId($id);
    }

    public function checkCodeAction(Request $request)
    {
        $code = $request->query->get('value');

        $exclude = $request->query->get('exclude');

        $avaliable = $this->getCategoryService()->isCategoryCodeAvaliable($code, $exclude);

        if ($avaliable) {
            $response = array('success' => true, 'message' => '');
        } else {
            $response = array('success' => false, 'message' => $this->getServiceKernel()->trans('????????????????????????????????????'));
        }

        return $this->createJsonResponse($response);
    }

    public function checkParentIdAction(Request $request)
    {
        $selectedParentId = $request->query->get('value');

        $currentId = $request->query->get('currentId');

        if ($currentId == $selectedParentId && $selectedParentId != 0) {
            $response = array('success' => false, 'message' => $this->getServiceKernel()->trans('?????????????????????????????????'));
        } else {
            $response = array('success' => true, 'message' => '');
        }

        return $this->createJsonResponse($response);
    }

    protected function renderTbody()
    {
        $categories = $this->getCategoryService()->getCategoryTree();

        return $this->render('TopxiaAdminBundle:ArticleCategory:tbody.html.twig', array(
            'categories'   => $categories,
            'categoryTree' => $categories
        ));
    }

    protected function getCategoryService()
    {
        return $this->getServiceKernel()->createService('Article.CategoryService');
    }

    protected function getArticleService()
    {
        return $this->getServiceKernel()->createService('Article.ArticleService');
    }
}
