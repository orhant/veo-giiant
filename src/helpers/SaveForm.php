<?php

namespace schmunk42\giiant\helpers;

use yii\helpers\Json;
use yii\helpers\StringHelper;

class SaveForm
{
    public static $savedFormList = false;

    public static function hint()
    {
        return ['savedForm' => 'Choose saved form ad load it data to form.'];
    }

    /**
     * get form attributes values.
     */
    public static function getFormAttributesValues($generator, $attributes)
    {
        $values = [];
        foreach ($attributes as $name) {
            $values[strtolower($name)] = [
                'value' => $generator->$name,
                'name' => $name,
            ];
        }

        return $values;
    }

    /**
     * walk through all modules gii directories and collect Giant crud generator saved forms.
     *
     * @param $generatorName
     * @return array|bool
     * @throws \ReflectionException
     */
    public static function loadSavedForms($generatorName, $giiInfoPath)
    {
        $suffix = str_replace(' ', '', $generatorName);

        if (self::$savedFormList) {
            return self::$savedFormList;
        }

        /*
         * get all possible gii directories with out validation on existing
         */
        $forms = [];
        self::buildJson(\Yii::getAlias('@app/' . $giiInfoPath), $forms, $suffix, 'app');
        if ($commonGiiDir = \Yii::getAlias('@common/' . $giiInfoPath, false)) {
            self::buildJson($commonGiiDir, $forms, $suffix,  'common');
        }
        if ($commonGiiDir = \Yii::getAlias('@console/models/' . $giiInfoPath, false)) {
            self::buildJson($commonGiiDir, $forms, $suffix,  'console');
        }
        foreach (\Yii::$app->modules as $moduleId => $module) {
            /*
             * get module base path
             */
            if (!is_array($module) && method_exists($module, 'getBasePath')) {
                $basePath = $module->getBasePath();
            } else {
                if(!class_exists($module['class'])){
                    \Yii::warning('Invalid class definition for module ' . $moduleId);
                    continue;
                }
                $reflector = new \ReflectionClass($module['class']);
                $basePath = StringHelper::dirname($reflector->getFileName());
            }
            $basePath .= '/' . $giiInfoPath;

            self::buildJson($basePath, $forms, $suffix, $moduleId);
        }

        return self::$savedFormList = $forms;
    }

    /**
     * from all gii directories collect forms
     * @param string $path
     * @param string $moduleId
     * @param array $forms
     * @param string $suffix
     */
    protected static function buildJson($path, &$forms, $suffix, $moduleId = NULL)
    {
        /*
         * search in module gii directory all forms json files
         * with required suffix
         */
        if (!file_exists($path)) {
            return;
        }

        $files = scandir($path);
        foreach ($files as $file) {
            if (!preg_match('#' . $suffix . '\.json$#', $file)) {
                continue;
            }
            $name = preg_replace('#' . $suffix . '\.json$#', '', $file);
            $forms[$moduleId . $name] = [
                'jsonData' => file_get_contents($path . '/' . $file),
                'label' => $moduleId . ' - ' . $name,
            ];
        }
    }

    /**
     * get array for form field "Saved form" data.
     *
     * @return array
     */
    public static function getSavedFormsListbox($generatorName, $giiInfoPath)
    {
        $r = ['0' => ' - '];
        foreach (self::loadSavedForms($generatorName, $giiInfoPath) as $k => $row) {
            $r[$k] = $row['label'];
        }

        return $r;
    }

    /**
     * creata js statement for seting to variable savedFormas array with all forms and it data in json format.
     *
     * @return string
     */
    public static function getSavedFormsJs($generatorName, $giiInfoPath)
    {
        $js = [];

        foreach (self::loadSavedForms($generatorName, $giiInfoPath) as $k => $row) {
            $js[] = $k.":'".Json::encode(Json::decode($row['jsonData']))."'";
        }

        return 'var savedForms = {'.str_replace('\\', '\\\\', implode(',', $js)).'};';
    }

    public static function jsFillForm()
    {
        return '
    function fillForm(id){
        if (id=="0") return;

        var formData = JSON.parse(savedForms[id]);
        
        for (var filedName in formData) {
        
            var checkboxName = "[name=\'Generator["+formData[filedName]["name"]+"][]\']";
            if(jQuery(checkboxName).is(":checkbox")){
                $(checkboxName).each(function( index ) {
                    $(this).prop("checked", false);
                    var actualValue = new String($( this ).val());
                    actualValue = actualValue + "";
                    for (var i = 0; i < formData[filedName]["value"].length; i++) {
                        var formValue = new String(formData[filedName]["value"][i]);
                        if(actualValue == formValue){
                            $(this).prop("checked", true);
                            continue;
                        }
                    }
                });                
                continue;
            }
            
            var checkboxName = "[name=\'Generator["+formData[filedName]["name"]+"]\']";
            if(jQuery(checkboxName).is(":checkbox")){
                jQuery(checkboxName).prop("checked", false);
                
                $(checkboxName).each(function( index ) {
                    $(checkboxName).prop("checked", false);
                    if(formData[filedName]["value"] == 1){
                        $(checkboxName).prop("checked", true);
                    }
                });                
                continue;
            }
            
            var fieldId = "generator-" + filedName;
            if (jQuery("#" + fieldId).is("input") || jQuery("#" + fieldId).is("select")){
                jQuery("#" + fieldId).val(formData[filedName]["value"]).trigger("input");
                continue;
            }    
        }    
    }
        ';
    }
}
