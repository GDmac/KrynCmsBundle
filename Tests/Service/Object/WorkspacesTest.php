<?php

namespace Kryn\CmsBundle\Tests\Service\Object;


use Kryn\CmsBundle\Propel\WorkspaceManager;
use Kryn\CmsBundle\Tests\KernelAwareTestCase;
use Kryn\Publication\Model\News;
use Kryn\Publication\Model\NewsCategory;
use Kryn\Publication\Model\NewsCategoryQuery;
use Kryn\Publication\Model\NewsQuery;
use Kryn\Publication\Model\NewsVersionQuery;

class WorkspacesTest extends KernelAwareTestCase
{

    public function testDifferentWorkspaces()
    {
        $this->getObjects()->clear('KrynPublicationBundle:News');

        WorkspaceManager::setCurrent(0);
        $this->getObjects()->add('KrynPublicationBundle:News', array(
            'title' => 'News 1 in workspace live'
        ));
        $this->getObjects()->add('KrynPublicationBundle:News', array(
            'title' => 'News 2 in workspace live'
        ));

        WorkspaceManager::setCurrent(1);
        $this->getObjects()->add('KrynPublicationBundle:News', array(
            'title' => 'News 1 in workspace one'
        ));
        $this->getObjects()->add('KrynPublicationBundle:News', array(
            'title' => 'News 2 in workspace one'
        ));
        $this->getObjects()->add('KrynPublicationBundle:News', array(
            'title' => 'News 3 in workspace one'
        ));

        //anything inserted and selecting works correctly?
        WorkspaceManager::setCurrent(0);
        $count = $this->getObjects()->getCount('KrynPublicationBundle:News');
        $this->assertEquals(2, $count);

        WorkspaceManager::setCurrent(1);
        $count = $this->getObjects()->getCount('KrynPublicationBundle:News');
        $this->assertEquals(3, $count);

        //anything inserted and selecting works correctly, also through propel directly?
        WorkspaceManager::setCurrent(0);
        $count = NewsQuery::create()->count();
        $this->assertEquals(2, $count);

        WorkspaceManager::setCurrent(1);
        $count = NewsQuery::create()->count();
        $this->assertEquals(3, $count);

    }

    public function testThroughCoreObjectWrapper()
    {
        $this->getObjects()->clear('KrynPublicationBundle:News');
        $count = $this->getObjects()->getCount('KrynPublicationBundle:News');
        $this->assertEquals(0, $count);

        $id11 = 0;

        for ($i = 1; $i <= 50; $i++) {
            $values = array(
                'title' => 'News ' . $i,
                'intro' => str_repeat('L', $i),
                'newsDate' => strtotime('+' . rand(1, 30) . ' day'. '+' . rand(1, 24) . ' hours')
            );
            $pk = $this->getObjects()->add('KrynPublicationBundle:News', $values);

            if ($i == 11) $id11 = $pk['id'];
        }

        $count = $this->getObjects()->getCount('KrynPublicationBundle:News');
        $this->assertEquals(50, $count);

        $item = $this->getObjects()->get('KrynPublicationBundle:News', $id11);
        $this->assertEquals('News 11', $item['title']);

        $this->getObjects()->update('KrynPublicationBundle:News', $id11, array(
            'title' => 'New News 11'
        ));

        $item = $this->getObjects()->get('KrynPublicationBundle:News', $id11);
        $this->assertEquals('New News 11', $item['title']);

        $this->getObjects()->update('KrynPublicationBundle:News', $id11, array(
            'title' => 'New News 11 - 2'
        ));

        //check version counter - we have 2 updates, so we have 2 versions.
        $count = NewsVersionQuery::create()->filterById($id11)->count();
        $this->assertEquals(2, $count);

        $this->getObjects()->remove('KrynPublicationBundle:News', $id11);

        //check version counter - we have 2 updates and 1 deletion (one deletion creates 2 new versions,
        //first regular version and second placeholder for deletion, so we have 4 versions now.
        $count = NewsVersionQuery::create()->filterById($id11)->count();
        $this->assertEquals(4, $count);

        $item = $this->getObjects()->get('KrynPublicationBundle:News', $id11);
        $this->assertNull($item); //should be gone

        $this->getObjects()->clear('KrynPublicationBundle:News');

    }

    public function testThroughPropelObjects()
    {
        NewsQuery::create()->deleteAll();
        NewsVersionQuery::create()->deleteAll();

        NewsCategoryQuery::create()->deleteAll();

        $category1 = new NewsCategory();
        $category1->setTitle('General');
        $category2 = new NewsCategory();
        $category2->setTitle('Company');

        $count = NewsQuery::create()->count();
        $this->assertEquals(0, $count);

        $id = 0;

        $items = [$category1, $category2];
        for ($i = 1; $i <= 50; $i++) {
            $object = new News();
            $object->setTitle('News ' . $i);
            $object->setIntro(str_repeat('L', $i));
            $object->setNewsDate(
                strtotime('+' . rand(1, 30) . ' day')
                + strtotime('+' . rand(1, 24) . ' hours', 0)
            );
            $object->setCategory($items[array_rand($items)]);
            $object->save();
            if ($i == 11)
                $id = $object->getId();
        }

        $count = NewsQuery::create()->count();
        $this->assertEquals(50, $count);

        /** @var News $item */
        $item = NewsQuery::create()->findOneById($id);
        $this->assertEquals('News 11', $item->getTitle());

        $item->setTitle('New News 11');
        $item->save();

        $item = NewsQuery::create()->findOneById($id);
        $this->assertEquals('New News 11', $item->getTitle());

        //check version counter - we have 1 update, so we have 1 version.
        $count = NewsVersionQuery::create()->filterById($id)->count();
        $this->assertEquals(1, $count);

        $item->delete();

        //check version counter - we have 1 update and 1 deletion (one deletion creates 2 new versions,
        //first regular version and second placeholder for deletion, so we have 3 versions now.
        $count = NewsVersionQuery::create()->filterById($id)->count();
        $this->assertEquals(3, $count);

        $item = NewsQuery::create()->findOneById($id);
        $this->assertNull($item); //should be gone

    }

}
