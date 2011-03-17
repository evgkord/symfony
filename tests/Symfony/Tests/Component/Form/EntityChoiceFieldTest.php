<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Tests\Component\Form;

require_once __DIR__.'/DoctrineOrmTestCase.php';
require_once __DIR__.'/Fixtures/SingleIdentEntity.php';
require_once __DIR__.'/Fixtures/CompositeIdentEntity.php';

use Symfony\Component\Form\EntityChoiceField;
use Symfony\Component\Form\Exception\UnexpectedTypeException;
use Symfony\Tests\Component\Form\Fixtures\SingleIdentEntity;
use Symfony\Tests\Component\Form\Fixtures\CompositeIdentEntity;
use Doctrine\ORM\Tools\SchemaTool;
use Doctrine\Common\Collections\ArrayCollection;

class EntityChoiceFieldTest extends DoctrineOrmTestCase
{
    const SINGLE_IDENT_CLASS = 'Symfony\Tests\Component\Form\Fixtures\SingleIdentEntity';

    const COMPOSITE_IDENT_CLASS = 'Symfony\Tests\Component\Form\Fixtures\CompositeIdentEntity';

    /**
     * @var EntityManager
     */
    private $em;

    protected function setUp()
    {
        parent::setUp();

        $this->em = $this->createTestEntityManager();

        $schemaTool = new SchemaTool($this->em);
        $classes = array(
            $this->em->getClassMetadata(self::SINGLE_IDENT_CLASS),
            $this->em->getClassMetadata(self::COMPOSITE_IDENT_CLASS),
        );

        try {
            $schemaTool->dropSchema($classes);
        } catch(\Exception $e) {
        }

        try {
            $schemaTool->createSchema($classes);
        } catch(\Exception $e) {
        }
    }

    protected function persist(array $entities)
    {
        foreach ($entities as $entity) {
            $this->em->persist($entity);
        }

        $this->em->flush();
        // no clear, because entities managed by the choice field must
        // be managed!
    }

//    public function testSetDataToUninitializedEntityWithNonRequired()
//    {
//        $entity1 = new SingleIdentEntity(1, 'Foo');
//        $entity2 = new SingleIdentEntity(2, 'Bar');
//
//        $this->persist(array($entity1, $entity2));
//
//        $field = $this->factory->getInstance('entity', 'name', array(
//            'em' => $this->em,
//            'class' => self::SINGLE_IDENT_CLASS,
//            'required' => false,
//            'property' => 'name'
//        ));
//
//        $this->assertEquals(array('' => '', 1 => 'Foo', 2 => 'Bar'), $field->getRenderer()->getVar('choices'));
//
//    }

    /**
     * @expectedException Symfony\Component\Form\Exception\UnexpectedTypeException
     */
    public function testConfigureQueryBuilderWithNonQueryBuilderAndNonClosure()
    {
        $field = $this->factory->getInstance('entity', 'name', array(
            'em' => $this->em,
            'class' => self::SINGLE_IDENT_CLASS,
            'query_builder' => new \stdClass(),
        ));
    }

    /**
     * @expectedException Symfony\Component\Form\Exception\UnexpectedTypeException
     */
    public function testConfigureQueryBuilderWithClosureReturningNonQueryBuilder()
    {
        $field = $this->factory->getInstance('entity', 'name', array(
            'em' => $this->em,
            'class' => self::SINGLE_IDENT_CLASS,
            'query_builder' => function () {
                return new \stdClass();
            },
        ));

        $field->submit('2');
    }

    /**
     * @expectedException Symfony\Component\Form\Exception\FormException
     */
    public function testChoicesMustBeManaged()
    {
        $entity1 = new SingleIdentEntity(1, 'Foo');
        $entity2 = new SingleIdentEntity(2, 'Bar');

        // no persist here!

        $field = $this->factory->getInstance('entity', 'name', array(
            'multiple' => false,
            'em' => $this->em,
            'class' => self::SINGLE_IDENT_CLASS,
            'choices' => array($entity1, $entity2),
            'property' => 'name',
        ));
    }

    public function testSetDataSingle_null()
    {
        $field = $this->factory->getInstance('entity', 'name', array(
            'multiple' => false,
            'em' => $this->em,
            'class' => self::SINGLE_IDENT_CLASS,
        ));
        $field->setData(null);

        $this->assertEquals(null, $field->getData());
        $this->assertEquals('', $field->getTransformedData());
    }

    public function testSetDataMultipleExpanded_null()
    {
        $field = $this->factory->getInstance('entity', 'name', array(
            'multiple' => true,
            'expanded' => true,
            'em' => $this->em,
            'class' => self::SINGLE_IDENT_CLASS,
        ));
        $field->setData(null);

        $this->assertEquals(null, $field->getData());
        $this->assertEquals(array(), $field->getTransformedData());
    }

    public function testSetDataMultipleNonExpanded_null()
    {
        $field = $this->factory->getInstance('entity', 'name', array(
            'multiple' => true,
            'expanded' => false,
            'em' => $this->em,
            'class' => self::SINGLE_IDENT_CLASS,
        ));
        $field->setData(null);

        $this->assertEquals(null, $field->getData());
        $this->assertEquals(array(), $field->getTransformedData());
    }

    public function testSubmitSingleExpanded_null()
    {
        $field = $this->factory->getInstance('entity', 'name', array(
            'multiple' => false,
            'expanded' => true,
            'em' => $this->em,
            'class' => self::SINGLE_IDENT_CLASS,
        ));
        $field->submit(null);

        $this->assertEquals(null, $field->getData());
        $this->assertEquals(array(), $field->getTransformedData());
    }

    public function testSubmitSingleNonExpanded_null()
    {
        $field = $this->factory->getInstance('entity', 'name', array(
            'multiple' => false,
            'expanded' => false,
            'em' => $this->em,
            'class' => self::SINGLE_IDENT_CLASS,
        ));
        $field->submit(null);

        $this->assertEquals(null, $field->getData());
        $this->assertEquals('', $field->getTransformedData());
    }

    public function testSubmitMultiple_null()
    {
        $field = $this->factory->getInstance('entity', 'name', array(
            'multiple' => true,
            'em' => $this->em,
            'class' => self::SINGLE_IDENT_CLASS,
        ));
        $field->submit(null);

        $this->assertEquals(new ArrayCollection(), $field->getData());
        $this->assertEquals(array(), $field->getTransformedData());
    }

    public function testSubmitSingleNonExpanded_singleIdentifier()
    {
        $entity1 = new SingleIdentEntity(1, 'Foo');
        $entity2 = new SingleIdentEntity(2, 'Bar');

        $this->persist(array($entity1, $entity2));

        $field = $this->factory->getInstance('entity', 'name', array(
            'multiple' => false,
            'expanded' => false,
            'em' => $this->em,
            'class' => self::SINGLE_IDENT_CLASS,
            'property' => 'name',
        ));

        $field->submit('2');

        $this->assertTrue($field->isTransformationSuccessful());
        $this->assertEquals($entity2, $field->getData());
        $this->assertEquals(2, $field->getTransformedData());
    }

    public function testSubmitSingleNonExpanded_compositeIdentifier()
    {
        $entity1 = new CompositeIdentEntity(10, 20, 'Foo');
        $entity2 = new CompositeIdentEntity(30, 40, 'Bar');

        $this->persist(array($entity1, $entity2));

        $field = $this->factory->getInstance('entity', 'name', array(
            'multiple' => false,
            'expanded' => false,
            'em' => $this->em,
            'class' => self::COMPOSITE_IDENT_CLASS,
            'property' => 'name',
        ));

        // the collection key is used here
        $field->submit('1');

        $this->assertTrue($field->isTransformationSuccessful());
        $this->assertEquals($entity2, $field->getData());
        $this->assertEquals(1, $field->getTransformedData());
    }

    public function testSubmitMultipleNonExpanded_singleIdentifier()
    {
        $entity1 = new SingleIdentEntity(1, 'Foo');
        $entity2 = new SingleIdentEntity(2, 'Bar');
        $entity3 = new SingleIdentEntity(3, 'Baz');

        $this->persist(array($entity1, $entity2, $entity3));

        $field = $this->factory->getInstance('entity', 'name', array(
            'multiple' => true,
            'expanded' => false,
            'em' => $this->em,
            'class' => self::SINGLE_IDENT_CLASS,
            'property' => 'name',
        ));

        $field->submit(array('1', '3'));

        $expected = new ArrayCollection(array($entity1, $entity3));

        $this->assertTrue($field->isTransformationSuccessful());
        $this->assertEquals($expected, $field->getData());
        $this->assertEquals(array(1, 3), $field->getTransformedData());
    }

    public function testSubmitMultipleNonExpanded_singleIdentifier_existingData()
    {
        $entity1 = new SingleIdentEntity(1, 'Foo');
        $entity2 = new SingleIdentEntity(2, 'Bar');
        $entity3 = new SingleIdentEntity(3, 'Baz');

        $this->persist(array($entity1, $entity2, $entity3));

        $field = $this->factory->getInstance('entity', 'name', array(
            'multiple' => true,
            'expanded' => false,
            'em' => $this->em,
            'class' => self::SINGLE_IDENT_CLASS,
            'property' => 'name',
        ));

        $existing = new ArrayCollection(array($entity2));

        $field->setData($existing);
        $field->submit(array('1', '3'));

        // entry with index 0 was removed
        $expected = new ArrayCollection(array(1 => $entity1, 2 => $entity3));

        $this->assertTrue($field->isTransformationSuccessful());
        $this->assertEquals($expected, $field->getData());
        // same object still, useful if it is a PersistentCollection
        $this->assertSame($existing, $field->getData());
        $this->assertEquals(array(1, 3), $field->getTransformedData());
    }

    public function testSubmitMultipleNonExpanded_compositeIdentifier()
    {
        $entity1 = new CompositeIdentEntity(10, 20, 'Foo');
        $entity2 = new CompositeIdentEntity(30, 40, 'Bar');
        $entity3 = new CompositeIdentEntity(50, 60, 'Baz');

        $this->persist(array($entity1, $entity2, $entity3));

        $field = $this->factory->getInstance('entity', 'name', array(
            'multiple' => true,
            'expanded' => false,
            'em' => $this->em,
            'class' => self::COMPOSITE_IDENT_CLASS,
            'property' => 'name',
        ));

        // because of the composite key collection keys are used
        $field->submit(array('0', '2'));

        $expected = new ArrayCollection(array($entity1, $entity3));

        $this->assertTrue($field->isTransformationSuccessful());
        $this->assertEquals($expected, $field->getData());
        $this->assertEquals(array(0, 2), $field->getTransformedData());
    }

    public function testSubmitMultipleNonExpanded_compositeIdentifier_existingData()
    {
        $entity1 = new CompositeIdentEntity(10, 20, 'Foo');
        $entity2 = new CompositeIdentEntity(30, 40, 'Bar');
        $entity3 = new CompositeIdentEntity(50, 60, 'Baz');

        $this->persist(array($entity1, $entity2, $entity3));

        $field = $this->factory->getInstance('entity', 'name', array(
            'multiple' => true,
            'expanded' => false,
            'em' => $this->em,
            'class' => self::COMPOSITE_IDENT_CLASS,
            'property' => 'name',
        ));

        $existing = new ArrayCollection(array(0 => $entity2));

        $field->setData($existing);
        $field->submit(array('0', '2'));

        // entry with index 0 was removed
        $expected = new ArrayCollection(array(1 => $entity1, 2 => $entity3));

        $this->assertTrue($field->isTransformationSuccessful());
        $this->assertEquals($expected, $field->getData());
        // same object still, useful if it is a PersistentCollection
        $this->assertSame($existing, $field->getData());
        $this->assertEquals(array(0, 2), $field->getTransformedData());
    }

    public function testSubmitSingleExpanded()
    {
        $entity1 = new SingleIdentEntity(1, 'Foo');
        $entity2 = new SingleIdentEntity(2, 'Bar');

        $this->persist(array($entity1, $entity2));

        $field = $this->factory->getInstance('entity', 'name', array(
            'multiple' => false,
            'expanded' => true,
            'em' => $this->em,
            'class' => self::SINGLE_IDENT_CLASS,
            'property' => 'name',
        ));

        $field->submit('2');

        $this->assertTrue($field->isTransformationSuccessful());
        $this->assertEquals($entity2, $field->getData());
        $this->assertSame(false, $field['1']->getData());
        $this->assertSame(true, $field['2']->getData());
        $this->assertSame('', $field['1']->getTransformedData());
        $this->assertSame('1', $field['2']->getTransformedData());
    }

    public function testSubmitMultipleExpanded()
    {
        $entity1 = new SingleIdentEntity(1, 'Foo');
        $entity2 = new SingleIdentEntity(2, 'Bar');
        $entity3 = new SingleIdentEntity(3, 'Bar');

        $this->persist(array($entity1, $entity2, $entity3));

        $field = $this->factory->getInstance('entity', 'name', array(
            'multiple' => true,
            'expanded' => true,
            'em' => $this->em,
            'class' => self::SINGLE_IDENT_CLASS,
            'property' => 'name',
        ));

        $field->submit(array('1' => '1', '3' => '3'));

        $expected = new ArrayCollection(array($entity1, $entity3));

        $this->assertTrue($field->isTransformationSuccessful());
        $this->assertEquals($expected, $field->getData());
        $this->assertSame(true, $field['1']->getData());
        $this->assertSame(false, $field['2']->getData());
        $this->assertSame(true, $field['3']->getData());
        $this->assertSame('1', $field['1']->getTransformedData());
        $this->assertSame('', $field['2']->getTransformedData());
        $this->assertSame('1', $field['3']->getTransformedData());
    }

    public function testOverrideChoices()
    {
        $entity1 = new SingleIdentEntity(1, 'Foo');
        $entity2 = new SingleIdentEntity(2, 'Bar');
        $entity3 = new SingleIdentEntity(3, 'Baz');

        $this->persist(array($entity1, $entity2, $entity3));

        $field = $this->factory->getInstance('entity', 'name', array(
            'em' => $this->em,
            'class' => self::SINGLE_IDENT_CLASS,
            // not all persisted entities should be displayed
            'choices' => array($entity1, $entity2),
            'property' => 'name',
        ));

        $field->submit('2');

        $this->assertEquals(array(1 => 'Foo', 2 => 'Bar'), $field->getRenderer()->getVar('choices'));
        $this->assertTrue($field->isTransformationSuccessful());
        $this->assertEquals($entity2, $field->getData());
        $this->assertEquals(2, $field->getTransformedData());
    }

    public function testDisallowChoicesThatAreNotIncluded_choices_singleIdentifier()
    {
        $entity1 = new SingleIdentEntity(1, 'Foo');
        $entity2 = new SingleIdentEntity(2, 'Bar');
        $entity3 = new SingleIdentEntity(3, 'Baz');

        $this->persist(array($entity1, $entity2, $entity3));

        $field = $this->factory->getInstance('entity', 'name', array(
            'em' => $this->em,
            'class' => self::SINGLE_IDENT_CLASS,
            'choices' => array($entity1, $entity2),
            'property' => 'name',
        ));

        $field->submit('3');

        $this->assertFalse($field->isTransformationSuccessful());
        $this->assertNull($field->getData());
    }

    public function testDisallowChoicesThatAreNotIncluded_choices_compositeIdentifier()
    {
        $entity1 = new CompositeIdentEntity(10, 20, 'Foo');
        $entity2 = new CompositeIdentEntity(30, 40, 'Bar');
        $entity3 = new CompositeIdentEntity(50, 60, 'Baz');

        $this->persist(array($entity1, $entity2, $entity3));

        $field = $this->factory->getInstance('entity', 'name', array(
            'em' => $this->em,
            'class' => self::COMPOSITE_IDENT_CLASS,
            'choices' => array($entity1, $entity2),
            'property' => 'name',
        ));

        $field->submit('2');

        $this->assertFalse($field->isTransformationSuccessful());
        $this->assertNull($field->getData());
    }

    public function testDisallowChoicesThatAreNotIncluded_queryBuilder_singleIdentifier()
    {
        $entity1 = new SingleIdentEntity(1, 'Foo');
        $entity2 = new SingleIdentEntity(2, 'Bar');
        $entity3 = new SingleIdentEntity(3, 'Baz');

        $this->persist(array($entity1, $entity2, $entity3));

        $repository = $this->em->getRepository(self::SINGLE_IDENT_CLASS);

        $field = $this->factory->getInstance('entity', 'name', array(
            'em' => $this->em,
            'class' => self::SINGLE_IDENT_CLASS,
            'query_builder' => $repository->createQueryBuilder('e')
                ->where('e.id IN (1, 2)'),
            'property' => 'name',
        ));

        $field->submit('3');

        $this->assertFalse($field->isTransformationSuccessful());
        $this->assertNull($field->getData());
    }

    public function testDisallowChoicesThatAreNotIncluded_queryBuilderAsClosure_singleIdentifier()
    {
        $entity1 = new SingleIdentEntity(1, 'Foo');
        $entity2 = new SingleIdentEntity(2, 'Bar');
        $entity3 = new SingleIdentEntity(3, 'Baz');

        $this->persist(array($entity1, $entity2, $entity3));

        $field = $this->factory->getInstance('entity', 'name', array(
            'em' => $this->em,
            'class' => self::SINGLE_IDENT_CLASS,
            'query_builder' => function ($repository) {
                return $repository->createQueryBuilder('e')
                        ->where('e.id IN (1, 2)');
            },
            'property' => 'name',
        ));

        $field->submit('3');

        $this->assertFalse($field->isTransformationSuccessful());
        $this->assertNull($field->getData());
    }

    public function testDisallowChoicesThatAreNotIncluded_queryBuilderAsClosure_compositeIdentifier()
    {
        $entity1 = new CompositeIdentEntity(10, 20, 'Foo');
        $entity2 = new CompositeIdentEntity(30, 40, 'Bar');
        $entity3 = new CompositeIdentEntity(50, 60, 'Baz');

        $this->persist(array($entity1, $entity2, $entity3));

        $field = $this->factory->getInstance('entity', 'name', array(
            'em' => $this->em,
            'class' => self::COMPOSITE_IDENT_CLASS,
            'query_builder' => function ($repository) {
                return $repository->createQueryBuilder('e')
                        ->where('e.id1 IN (10, 50)');
            },
            'property' => 'name',
        ));

        $field->submit('2');

        $this->assertFalse($field->isTransformationSuccessful());
        $this->assertNull($field->getData());
    }
}