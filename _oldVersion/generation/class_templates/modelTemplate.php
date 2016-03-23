<?='<?php'?>

<?$cd = $this->classData;?>
<?$conf = Zend_Registry::get('config');?>
/**
 * base<?=$cd['filename']?>.php
 *
 * model for table <?=$cd['table']?> 
 *
 * PHP version 5
 *
 * @author     <?=$conf->project->author?> 
 * @copyright  <?=$conf->project->copyright?> 
 */

class Model_<?=$cd['class_name']?> extends <?=$cd['base_class_name']?> {

}