<?php

// No direct access
defined('_JEXEC') or die('Restricted access');

// Require the abstract plugin class
require_once COM_FABRIK_FRONTEND . '/models/plugin-form.php';

class PlgFabrik_FormGateway_to_task extends PlgFabrik_Form
{
    public $execute = true;
    private $actualParams;
    public function onBeforeProcess()
    {
        if ($this->execute === true) {
            $params = $this->getParams();

            $condition = $params->get('gateway_to_task_condition', NULL);

            $conditionExecute = true;
            if ($condition !== NULL) {
                $conditionExecute = eval($condition);
            }

            $elementTable = $params->get('gateway_to_task_table', NULL);
            $elementId = $params->get('gateway_to_task_element', NULL);
            $elementRowId = $params->get('gateway_to_task_rowid', NULL);

            if ((!is_null($elementId)) && (!is_null($elementTable)) && (!is_null($elementRowId)) && ($conditionExecute)) {
                $worker = FabrikWorker::getPluginManager();
                $elementName = $worker->getElementPlugin($elementId)->element->name;

                $json = $this->getElementJson($elementTable, $elementName, $elementRowId);
                $this->addJsonToForm($json);
                $this->removeJson();
            }
        }
    }

    private function getElementJson($table, $elementName, $rowId) {
        if (empty($table)) {
            return '';
        }

        $db = JFactory::getDbo();
        $query = $db->getQuery(true);
        $query->select('db_table_name')->from('#__fabrik_lists')->where("id = '{$table}'");
        $db->setQuery($query);
        $tableName = $db->loadResult();

        if (empty($tableName)) {
            return '';
        }

        $query = $db->getQuery(true);
        $query->select($elementName)->from($tableName)->where("id = '{$rowId}'");
        $db->setQuery($query);

        return $db->loadResult();
    }

    private function addJsonToForm($json) {
        if (empty($json)) {
            return;
        }

        $formModel = $this->getModel();
        $formId = $formModel->getId();

        $db = JFactory::getDbo();
        $query = $db->getQuery(true);
        $query->select('params')->from('#__fabrik_forms')->where("id = '{$formId}'");
        $db->setQuery($query);
        $this->actualParams = $db->loadResult();
        $actualParams = (array) json_decode($this->actualParams);

        $json = (array) json_decode($json);

        foreach ($json as $key => $item) {
            if ((is_array($item)) && key_exists($key, $actualParams)) {
                $actualParams[$key] = array_merge($actualParams[$key], $item);
            }
            else {
                $actualParams[$key] = $item;
            }
        }

        $update = new stdClass();
        $update->id = $formId;
        $update->params = json_encode((object) $actualParams);
        $db->updateObject('#__fabrik_forms', $update, 'id');

        $model = JModelLegacy::getInstance('Form', 'FabrikFEModel');
        $model->setId($formModel->getId());
        $model->setRowId($formModel->formData[$formModel->getTableName . '___id']);
        $this->execute = false;
        $model->process();
    }

    private function removeJson() {
        $formModel = $this->getModel();

        $update = new stdClass();
        $update->id = $formModel->getId();
        $update->params = $this->actualParams;
        JFactory::getDbo()->updateObject('#__fabrik_forms', $update, 'id');
    }

}