<?php

namespace SilverStripe\SpamProtection\Tests;

use SilverStripe\UserForms\Model\UserDefinedForm;
use SilverStripe\Core\Config\Config;
use SilverStripe\Dev\SapphireTest;
use SilverStripe\Forms\FieldGroup;
use SilverStripe\Forms\Form;
use SilverStripe\Forms\RequiredFields;
use SilverStripe\Forms\TextField;
use SilverStripe\SpamProtection\EditableSpamProtectionField;
use SilverStripe\SpamProtection\Extension\FormSpamProtectionExtension;
use SilverStripe\SpamProtection\Tests\Stub\Protector;

class EditableSpamProtectionFieldTest extends SapphireTest
{
    protected $usesDatabase = true;

    protected function setUp()
    {
        parent::setUp();

        if (!class_exists(EditableSpamProtectionField::class)) {
            $this->markTestSkipped('"userforms" module not installed');
        }

        Config::modify()->set(
            FormSpamProtectionExtension::class,
            'default_spam_protector',
            Protector::class
        );
    }

    public function testValidateFieldDoesntAddErrorOnSuccess()
    {
        $formMock = $this->getFormMock();
        $formFieldMock = $this->getEditableFormFieldMock();

        $formFieldMock
            ->getFormField() // mock
            ->expects($this->once())
            ->method('validate')
            ->will($this->returnValue(true));

        $formMock
            ->expects($this->never())
            ->method('sessionMessage');

        $formFieldMock->validateField(array('MyField' => null), $formMock);
    }

    public function testValidateFieldAddsErrorFromField()
    {
        $formMock = $this->getFormMock();
        $formFieldMock = $this->getEditableFormFieldMock();

        $formFieldMock
            ->getFormField() // mock
            ->expects($this->once())
            ->method('validate')
            ->will($this->returnValue(false));

        $formMock->getValidator()->validationError('MyField', 'some field message', 'required');

        $formMock
            ->expects($this->once())
            ->method('sessionMessage')
            ->with($this->stringContains('some field message'), $this->anything());

        $formFieldMock->validateField(array('MyField' => null), $formMock);
    }

    public function testValidateFieldAddsDefaultError()
    {
        $formMock = $this->getFormMock();
        $formFieldMock = $this->getEditableFormFieldMock();

        $formFieldMock
            ->getFormField() // mock
            ->expects($this->once())
            ->method('validate')
            ->will($this->returnValue(false));

        // field doesn't set any validation errors here

        $formMock
            ->expects($this->once())
            ->method('sessionError')
            ->with($this->stringContains('default error message'));

        $formFieldMock->validateField(array('MyField' => null), $formMock);
    }

    public function testSpamConfigurationShowsInCms()
    {
        $field = $this->getEditableFormFieldMock();
        $fields = $field->getCMSFields();

        $this->assertInstanceOf(FieldGroup::class, $fields->fieldByName('Root.Main.SpamFieldMapping'));
    }

    public function testSpamMapSettingsAreSerialised()
    {
        $field = $this->getEditableFormFieldMock();
        $field->SpamFieldSettings = json_encode(array('foo' => 'bar', 'bar' => 'baz'));
        $field->write();

        $this->assertJson($field->SpamFieldSettings);
        $this->assertSame('bar', $field->spamMapValue('foo'));
        $this->assertSame('baz', $field->spamMapValue('bar'));
    }

    protected function getFormMock()
    {
        $formMock = $this->getMockBuilder(Form::class)
            ->setMethods(['sessionMessage', 'sessionError', 'getValidator'])
            ->disableOriginalConstructor()
            ->getMock();

        $formMock
            ->expects($this->any())
            ->method('getValidator')
            ->will($this->returnValue(new RequiredFields()));

        return $formMock;
    }

    protected function getEditableFormFieldMock()
    {
        $page = new UserDefinedForm();
        $page->write();

        $formFieldMock = $this->getMockBuilder(TextField::class)
            ->disableOriginalConstructor()
            ->getMock();

        $editableFormFieldMock = new EditableSpamProtectionField(array(
            'ParentID' => $page->ID,
            'Name' => 'MyField',
            'CustomErrorMessage' => 'default error message'
        ));
        $editableFormFieldMock->write();
        $editableFormFieldMock->setFormField($formFieldMock);

        return $editableFormFieldMock;
    }
}
