<?php
namespace Topxia\Service\Article\Impl;

use Topxia\Common\ArrayToolkit;
use Topxia\Service\Common\BaseService;
use Topxia\Service\Article\CategoryService;

class CategoryServiceImpl extends BaseService implements CategoryService
{
    public function getCategory($id)
    {
        if (empty($id)) {
            return;
        }

        return $this->getCategoryDao()->getCategory($id);
    }

    public function getCategoryByCode($code)
    {
        return $this->getCategoryDao()->findCategoryByCode($code);
    }

    public function getCategoryTree()
    {
        $prepare = function ($categories) {
            $prepared = array();

            foreach ($categories as $category) {
                if (!isset($prepared[$category['parentId']])) {
                    $prepared[$category['parentId']] = array();
                }

                $prepared[$category['parentId']][] = $category;
            }

            return $prepared;
        };

        $categories = $prepare($this->findAllCategories());
        $tree       = array();
        $this->makeCategoryTree($tree, $categories, 0);

        return $tree;
    }

    protected function makeCategoryTree(&$tree, &$categories, $parentId)
    {
        static $depth = 0;
        static $leaf  = false;

        if (isset($categories[$parentId]) && is_array($categories[$parentId])) {
            foreach ($categories[$parentId] as $category) {
                $depth++;
                $category['depth'] = $depth;
                $tree[]            = $category;
                $this->makeCategoryTree($tree, $categories, $category['id']);
                $depth--;
            }
        }

        return $tree;
    }

    public function findCategoryChildrenIds($id)
    {
        $category = $this->getCategory($id);

        if (empty($category)) {
            return array();
        }

        $tree = $this->getCategoryTree();

        $childrenIds = array();
        $depth       = 0;

        foreach ($tree as $node) {
            if ($node['id'] == $category['id']) {
                $depth = $node['depth'];
                continue;
            }

            if ($depth > 0 && $depth < $node['depth']) {
                $childrenIds[] = $node['id'];
            }

            if ($depth > 0 && $depth >= $node['depth']) {
                break;
            }
        }

        return $childrenIds;
    }

    public function findCategoriesByIds(array $ids)
    {
        return ArrayToolkit::index($this->getCategoryDao()->findCategoriesByIds($ids), 'id');
    }

    public function findAllCategories()
    {
        return $this->getCategoryDao()->findAllCategories();
    }

    public function isCategoryCodeAvaliable($code, $exclude = null)
    {
        if (empty($code)) {
            return false;
        }

        if ($code == $exclude) {
            return true;
        }

        $category = $this->getCategoryDao()->findCategoryByCode($code);

        return $category ? false : true;
    }

    public function getCategoryByParentId($parentId)
    {
        return $this->getCategoryDao()->getCategoryByParentId($parentId);
    }

    public function findAllCategoriesByParentId($parentId)
    {
        return ArrayToolkit::index($this->getCategoryDao()->findAllCategoriesByParentId($parentId), 'id');
    }

    public function findAllPublishedCategoriesByParentId($parentId)
    {
        return ArrayToolkit::index($this->getCategoryDao()->findAllPublishedCategoriesByParentId($parentId), 'id');
    }

    public function findCategoryBreadcrumbs($categoryId)
    {
        $breadcrumbs = array();

        $categoryTree = $this->getCategoryTree();

        $indexedCategories = ArrayToolkit::index($categoryTree, 'id');

        while (true) {
            if (empty($indexedCategories[$categoryId])) {
                break;
            }

            $category      = $indexedCategories[$categoryId];
            $breadcrumbs[] = $category;

            if (empty($category['parentId'])) {
                break;
            }

            $categoryId = $category['parentId'];
        }

        return array_reverse($breadcrumbs);
    }

    public function createCategory(array $category)
    {
        $category = ArrayToolkit::parts($category, array('name', 'code', 'weight', 'parentId', 'publishArticle', 'seoTitle', 'seoKeyword', 'seoDesc', 'published'));

        if (!ArrayToolkit::requireds($category, array('name', 'code', 'weight', 'parentId'))) {
            throw $this->createServiceException("??????????????????????????????????????????");
        }

        $this->_filterCategoryFields($category);

        $category['createdTime'] = time();

        $category = $this->getCategoryDao()->addCategory($category);

        $this->getLogService()->info('category', 'create', "???????????? {$category['name']}(#{$category['id']})", $category);

        return $category;
    }

    public function updateCategory($id, array $fields)
    {
        $category = $this->getCategory($id);

        if (empty($category)) {
            throw $this->createNoteFoundException("??????(#{$id})?????????????????????????????????");
        }

        $fields = ArrayToolkit::parts($fields, array('name', 'code', 'weight', 'parentId', 'publishArticle', 'seoTitle', 'seoKeyword', 'seoDesc', 'published'));

        if (empty($fields)) {
            throw $this->createServiceException('???????????????????????????????????????');
        }

        $this->_filterCategoryFields($fields);

        $this->getLogService()->info('category', 'update', "???????????? {$fields['name']}(#{$id})", $fields);

        return $this->getCategoryDao()->updateCategory($id, $fields);
    }

    public function deleteCategory($id)
    {
        $category = $this->getCategory($id);

        if (empty($category)) {
            throw $this->createNotFoundException();
        }

        $ids   = $this->findCategoryChildrenIds($id);
        $ids[] = $id;

        foreach ($ids as $id) {
            $this->getCategoryDao()->deleteCategory($id);
        }

        $this->getLogService()->info('category', 'delete', "????????????{$category['name']}(#{$id})");
    }

    protected function _filterCategoryFields($fields)
    {
        $fields = ArrayToolkit::filter($fields, array(
            'name'           => '',
            'code'           => '',
            'weight'         => 0,
            'publishArticle' => '',
            'seoTitle'       => '',
            'seoDesc'        => '',
            'published'      => 1,
            'parentId'       => 0
        ));

        if (empty($fields['name'])) {
            throw $this->createServiceException("???????????????????????????????????????");
        }

        if (empty($fields['code'])) {
            throw $this->createServiceException("???????????????????????????????????????");
        } else {
            if (!preg_match("/^[a-zA-Z0-9_]+$/i", $fields['code'])) {
                throw $this->createServiceException("??????({$fields['code']})???????????????????????????????????????");
            }

            if (ctype_digit($fields['code'])) {
                throw $this->createServiceException("??????({$fields['code']})???????????????????????????????????????");
            }
        }

        return $fields;
    }

    public function makeNavCategories($code)
    {
        $rootCagoies = $this->findAllPublishedCategoriesByParentId(0);

        if (empty($code)) {
            return array($rootCagoies, array(), array());
        } else {
            $category    = $this->getCategoryByCode($code);
            $parentId    = $category['id'];
            $categories  = array();
            $activeIds   = array();
            $activeIds[] = $category['id'];
            $level       = 1;

            while ($parentId) {
                $activeIds[] = $parentId;
                $sibling     = $this->findAllPublishedCategoriesByParentId($parentId);

                if ($sibling) {
                    $categories[$level] = $sibling;
                    $level++;
                }

                $parent   = $this->getCategory($parentId);
                $parentId = $parent['parentId'];
            }

            $categories = array_reverse($categories);

            return array($rootCagoies, $categories, $activeIds);
        }
    }

    public function findCategoriesCountByParentId($parentId)
    {
        return $this->getCategoryDao()->findCategoriesCountByParentId($parentId);
    }

    protected function getCategoryDao()
    {
        return $this->createDao('Article.CategoryDao');
    }

    protected function getLogService()
    {
        return $this->createService('System.LogService');
    }
}
