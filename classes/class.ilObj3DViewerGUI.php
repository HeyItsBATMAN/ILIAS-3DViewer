<?php

include_once("./Services/Repository/classes/class.ilObjectPluginGUI.php");
require_once("./Services/Form/classes/class.ilPropertyFormGUI.php");
require_once("./Services/Form/classes/class.ilTextInputGUI.php");
require_once("./Services/Form/classes/class.ilCheckboxInputGUI.php");
require_once("./Services/Tracking/classes/class.ilLearningProgress.php");
require_once("./Services/Tracking/classes/class.ilLPStatusWrapper.php");
require_once("./Services/Tracking/classes/status/class.ilLPStatusPlugin.php");
require_once("./Customizing/global/plugins/Services/Repository/RepositoryObject/3DViewer/classes/class.il3DViewerPlugin.php");
require_once("./Services/Form/classes/class.ilPropertyFormGUI.php");
require_once("./Services/Form/classes/class.ilNonEditableValueGUI.php");

/**
 * @ilCtrl_isCalledBy ilObj3DViewerGUI: ilRepositoryGUI, ilAdministrationGUI, ilObjPluginDispatchGUI
 * @ilCtrl_Calls ilObj3DViewerGUI: ilPermissionGUI, ilInfoScreenGUI, ilObjectCopyGUI, ilCommonActionDispatcherGUI, ilExportGUI
 */
class ilObj3DViewerGUI extends ilObjectPluginGUI
{
    const LP_SESSION_ID = 'x3dv_lp_session_state';
    /** @var  ilTemplate */
    public $tpl;
    /** @var  ilCtrl */
    protected $ctrl;
    /** @var  ilTabsGUI */
    protected $tabs;

    /**
     * Get type.
     */
    final function getType()
    {
        return il3DViewerPlugin::ID;
    }

    /**
     * Handles all commmands of this class, centralizes permission checks
     */
    function performCommand($cmd)
    {
        switch ($cmd) {
            case "editProperties":
                $this->editProperties();
                break;  // list all commands that need write permission here
            case "updateProperties":
                break;
            case "saveProperties":
                $this->saveProperties();
                break;
            case "showContent":
                $this->showContent();
                break;   // list all commands that need read permission here
            case "setStatusToCompleted":
                $this->setStatusToCompleted();
                break;
            case "setStatusToFailed":
                $this->setStatusToFailed();
                break;
            case "setStatusToInProgress":
                $this->setStatusToInProgress();
                break;
            case "setStatusToNotAttempted":
                $this->setStatusToNotAttempted();
                break;
                $this->checkPermission("read");
                $this->$cmd();
                break;
        }
    }

    /**
     * After object has been created -> jump to this command
     */
    function getAfterCreationCmd()
    {
        return "editProperties";
    }

    /**
     * Set tabs
     */
    function setTabs()
    {
        global $ilCtrl, $ilAccess;

        // tab for the "show content" command
        if ($ilAccess->checkAccess("read", "", $this->object->getRefId())) {
            $this->tabs->addTab("content", $this->txt("content"), $ilCtrl->getLinkTarget($this, "showContent"));
        }

        // standard info screen tab
        $this->addInfoTab();

        // a "properties" tab
        if ($ilAccess->checkAccess("write", "", $this->object->getRefId())) {
            $this->tabs->addTab("properties", $this->txt("properties"), $ilCtrl->getLinkTarget($this, "editProperties"));
        }

        // standard export tab
        $this->addExportTab();

        // standard permission tab
        $this->addPermissionTab();
        $this->activateTab();
    }

    /**
     * We need this method if we can't access the tabs otherwise...
     */
    private function activateTab()
    {
        $next_class = $this->ctrl->getCmdClass();

        switch ($next_class) {
            case 'ilexportgui':
                $this->tabs->activateTab("export");
                break;
        }

        return;
    }

//
// DISPLAY TABS
//

    /**
     * Initialisation
     */
    protected function afterConstructor()
    {
        global $ilCtrl, $ilTabs, $tpl;
        $this->ctrl = $ilCtrl;
        $this->tabs = $ilTabs;
        $this->tpl = $tpl;
    }

    /**
     * Edit Properties. This commands uses the form class to display an input form.
     */
    protected function editProperties()
    {
        $this->tabs->activateTab("properties");
        $form = $this->initPropertiesForm();
        $this->addValuesToForm($form);
        $this->tpl->setContent($form->getHTML());
    }

    /**
     * @return ilPropertyFormGUI
     */
    protected function initPropertiesForm()
    {
        $form = new ilPropertyFormGUI();
        $form->setTitle($this->plugin->txt("obj_x3dv"));

        $title = new ilTextInputGUI($this->plugin->txt("title"), "title");
        $title->setRequired(true);
        $form->addItem($title);

        $description = new ilTextInputGUI($this->plugin->txt("description"), "description");
        $form->addItem($description);

        $online = new ilCheckboxInputGUI($this->plugin->txt("online"), "online");
        $form->addItem($online);

        $form->setFormAction($this->ctrl->getFormAction($this, "saveProperties"));
        $form->addCommandButton("saveProperties", $this->plugin->txt("update"));

        return $form;
    }

    /**
     * @param $form ilPropertyFormGUI
     */
    protected function addValuesToForm(&$form)
    {
        $form->setValuesByArray(array(
            "title" => $this->object->getTitle(),
            "description" => $this->object->getDescription(),
            "online" => $this->object->isOnline(),
        ));
    }

    /**
     *
     */
    protected function saveProperties()
    {
        $form = $this->initPropertiesForm();
        $form->setValuesByPost();
        if ($form->checkInput()) {
            $this->fillObject($this->object, $form);
            $this->object->update();
            ilUtil::sendSuccess($this->plugin->txt("update_successful"), true);
            $this->ctrl->redirect($this, "editProperties");
        }
        $this->tpl->setContent($form->getHTML());
    }

    /**
     * @param $object ilObj3DViewer
     * @param $form ilPropertyFormGUI
     */
    private function fillObject($object, $form)
    {
        $object->setTitle($form->getInput('title'));
        $object->setDescription($form->getInput('description'));
        $object->setOnline($form->getInput('online'));
    }

    protected function showContent()
    {
        $this->tabs->activateTab("content");

        /** @var ilObj3DViewer $object */
        $object = $this->object;

        $form = new ilPropertyFormGUI();
        $form->setTitle($object->getTitle());

        $i = new ilNonEditableValueGUI();
        $i->setInfo("<h1> Hello World </h1>" . $this->plugin->txt("set_in_progress"));
        $form->addItem($i);

        $this->tpl->setContent($form->getHTML());
    }

    protected function setStatusToFailed()
    {
        $this->setStatusAndRedirect(ilLPStatus::LP_STATUS_FAILED_NUM);
    }

    private function setStatusAndRedirect($status)
    {
        global $ilUser;
        $_SESSION[self::LP_SESSION_ID] = $status;
        ilLPStatusWrapper::_updateStatus($this->object->getId(), $ilUser->getId());
        $this->ctrl->redirect($this, $this->getStandardCmd());
    }

    /**
     * Get standard command
     */
    function getStandardCmd()
    {
        return "showContent";
    }

    protected function setStatusToInProgress()
    {
        $this->setStatusAndRedirect(ilLPStatus::LP_STATUS_IN_PROGRESS_NUM);
    }

    protected function setStatusToNotAttempted()
    {
        $this->setStatusAndRedirect(ilLPStatus::LP_STATUS_NOT_ATTEMPTED_NUM);
    }

    private function setStatusToCompleted()
    {
        $this->setStatusAndRedirect(ilLPStatus::LP_STATUS_COMPLETED_NUM);
    }
}

?>