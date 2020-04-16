<?php

namespace srag\CustomInputGUIs\SrGoogleAccountAuth\FormBuilder;

use Closure;
use Exception;
use ilFormPropertyDispatchGUI;
use ILIAS\UI\Component\Input\Container\Form\Form;
use ILIAS\UI\Component\Input\Field\DependantGroupProviding;
use ILIAS\UI\Component\Input\Field\Radio as RadioInterface;
use ILIAS\UI\Implementation\Component\Input\Field\Group;
use ILIAS\UI\Implementation\Component\Input\Field\Radio;
use ilSubmitButton;
use ilUtil;
use srag\CustomInputGUIs\SrGoogleAccountAuth\InputGUIWrapperUIInputComponent\InputGUIWrapperUIInputComponent;
use srag\DIC\SrGoogleAccountAuth\DICTrait;
use Throwable;

/**
 * Class AbstractFormBuilder
 *
 * @package      srag\CustomInputGUIs\SrGoogleAccountAuth\FormBuilder
 *
 * @author       studer + raimann ag - Team Custom 1 <support-custom1@studer-raimann.ch>
 *
 * @ilCtrl_Calls srag\CustomInputGUIs\SrGoogleAccountAuth\FormBuilder\AbstractFormBuilder: ilFormPropertyDispatchGUI
 */
abstract class AbstractFormBuilder implements FormBuilder
{

    use DICTrait;
    /**
     * @var object
     */
    protected $parent;
    /**
     * @var Form|null
     */
    protected $form = null;


    /**
     * AbstractFormBuilder constructor
     *
     * @param object $parent
     */
    public function __construct(object $parent)
    {
        $this->parent = $parent;
    }


    /**
     * @return Form
     */
    protected function buildForm() : Form
    {
        $form = self::dic()->ui()->factory()->input()->container()->form()->standard($this->getAction(), [
            "form" => self::dic()->ui()->factory()->input()->field()->section($this->getFields(), $this->getTitle())
        ]);

        $this->setDataToForm($form);

        return $form;
    }


    /**
     *
     */
    public function executeCommand() : void
    {
        $next_class = self::dic()->ctrl()->getNextClass($this);

        switch (strtolower($next_class)) {
            case strtolower(ilFormPropertyDispatchGUI::class):
                foreach ($this->getForm()->getInputs()["form"]->getInputs() as $input) {
                    if ($input instanceof InputGUIWrapperUIInputComponent) {
                        if ($input->getInput()->getPostVar() === strval(filter_input(INPUT_GET, "postvar"))) {
                            $form_dispatcher = new ilFormPropertyDispatchGUI();
                            $form_dispatcher->setItem($input->getInput());
                            self::dic()->ctrl()->forwardCommand($form_dispatcher);
                            break;
                        }
                    }
                }
                break;

            default:
                break;
        }
    }


    /**
     * @return string
     */
    protected function getAction() : string
    {
        return self::dic()->ctrl()->getFormAction($this->parent);
    }


    /**
     * @return array
     */
    protected abstract function getButtons() : array;


    /**
     * @return array
     */
    protected abstract function getData() : array;


    /**
     * @return array
     */
    protected abstract function getFields() : array;


    /**
     * @inheritDoc
     */
    public function getForm() : Form
    {
        if ($this->form === null) {
            $this->form = $this->buildForm();
        }

        return $this->form;
    }


    /**
     * @return string
     */
    protected abstract function getTitle() : string;


    /**
     * @inheritDoc
     */
    public function render() : string
    {
        $html = self::output()->getHTML($this->getForm());

        $html = $this->setButtonsToForm($html);

        return $html;
    }


    /**
     * @param string $html
     *
     * @return string
     */
    protected function setButtonsToForm(string $html) : string
    {
        $html = preg_replace_callback('/(<button\s+class\s*=\s*"btn btn-default"\s+data-action\s*=\s*"#"\s+id\s*=\s*"[a-z0-9_]+"\s*>)(.+)(<\/button\s*>)/',
            function (array $matches) : string {
                $buttons = [];

                foreach ($this->getButtons() as $cmd => $label) {
                    if (!empty($buttons)) {
                        $buttons[] = "&nbsp;";
                    }

                    $button = ilSubmitButton::getInstance();

                    $button->setCommand($cmd);

                    $button->setCaption($label, false);

                    $buttons[] = $button;
                }

                return self::output()->getHTML($buttons);
            }, $html);

        return $html;
    }


    /**
     * @param Form $form
     */
    protected function setDataToForm(Form $form) : void
    {
        $data = $this->getData();

        $inputs = $form->getInputs()["form"]->getInputs();
        foreach ($inputs as $key => $field) {
            if (isset($data[$key])) {
                if ($field instanceof DependantGroupProviding && !empty($field->getDependantGroup())) {
                    $inputs2 = $field->getDependantGroup()->getInputs();
                    if (!empty($inputs2)) {
                        if (isset($data[$key]["value"])) {
                            try {
                                $inputs[$key] = $field = $field->withValue($data[$key]["value"]);
                            } catch (Throwable $ex) {

                            }
                        }
                        $data2 = (isset($data[$key]["group_values"]) ? $data[$key]["group_values"] : $data[$key])["dependant_group"];
                        foreach ($inputs2 as $key2 => $field2) {
                            if (isset($data2[$key2])) {
                                try {
                                    $inputs2[$key2] = $field2 = $field2->withValue($data2[$key2]);
                                } catch (Throwable $ex) {

                                }
                            }
                        }
                        Closure::bind(function () use ($inputs2): void {
                            $this->inputs = $inputs2;
                        }, $field->getDependantGroup(), Group::class)();
                        continue;
                    }
                } else {
                    if ($field instanceof RadioInterface
                        && isset($data[$key]["value"])
                        && !empty($inputs2 = Closure::bind(function () use ($data, $key) : array {
                            return $this->dependant_fields[$data[$key]["value"]];
                        }, $field, Radio::class)())
                    ) {
                        try {
                            $inputs[$key] = $field = $field->withValue($data[$key]["value"]);
                        } catch (Throwable $ex) {

                        }
                        $data2 = $data[$key]["group_values"];
                        foreach ($inputs2 as $key2 => $field2) {
                            if (isset($data2[$key2])) {
                                try {
                                    $inputs2[$key2] = $field2 = $field2->withValue($data2[$key2]);
                                } catch (Throwable $ex) {

                                }
                            }
                        }
                        Closure::bind(function () use ($data, $key, $inputs2): void {
                            $this->dependant_fields[$data[$key]["value"]] = $inputs2;
                        }, $field, Radio::class)();
                        continue;
                    }
                }
            }
            try {
                $inputs[$key] = $field = $field->withValue($data[$key]);
            } catch (Throwable $ex) {

            }
        }
        Closure::bind(function () use ($inputs): void {
            $this->inputs = $inputs;
        }, $form->getInputs()["form"], Group::class)();
    }


    /**
     * @inheritDoc
     */
    public function storeForm() : bool
    {
        try {
            $this->form = $this->getForm()->withRequest(self::dic()->http()->request());

            $data = $this->form->getData();

            if (empty($data)) {
                throw new Exception();
            }

            $this->storeData($data["form"] ?? []);
        } catch (Throwable $ex) {
            ilUtil::sendFailure(self::dic()->language()->txt("form_input_not_valid"));

            return false;
        }

        return true;
    }


    /**
     * @param array $data
     */
    protected abstract function storeData(array $data) : void;
}
