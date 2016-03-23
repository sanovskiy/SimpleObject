<?='<?php'?>

<?$cd = $this->classData;
$conf = Zend_Registry::get('config');?>
/**');
 * base<?=$cd['filename']?>.php
 *
 * base model for table <?=$cd['table']?> 
 *
 * PHP version 5
 *
 * @author     <?=$conf->project->author?> 
 * @copyright  <?=$conf->project->copyright?> 
 */

class <?=$cd['base_class_name']?> extends SimpleObject_Abstract {

    public $DBTable = '<?=$cd['table']?>';
    protected $TFields = array(
    <?foreach ($cd['fields'] as $num => $f) :?>
        <?=$num?> => '<?=$f['name']?>',
    <?endforeach?>

    );

    protected $Properties = array(
    <?foreach ($cd['fields'] as $num => $f) :?>
        <?=$num?> => '<?=$f['property_name']?>',
    <?endforeach?>

    );

    protected $field2PropertyTransform = array(
    <?foreach ($cd['fields'] as $num => $f) :?>
    <?if (isset($f['field2PropertyTransform'])) :?>
        <?=$num?> => '<?=$f['field2PropertyTransform']?>',
    <?endif?>
    <?endforeach?>

    );
    
    protected $property2FieldTransform = array(
    <?foreach ($cd['fields'] as $num => $f) :?>
    <?if (isset($f['property2FieldTransform'])) :?>
        <?=$num?> => '<?=$f['property2FieldTransform']?>',
    <?endif?>
    <?endforeach?>

    );
    
    protected $field2ReturnTransform = array(
    <?foreach ($cd['fields'] as $num => $f) :?>
    <?if (isset($f['field2ReturnTransform'])) :?>
        <?=$num?> => '<?=$f['field2ReturnTransform']?>',
    <?endif?>
    <?endforeach?>

    );

    <?foreach ($cd['fields'] as $f) :?>
    public $<?=$f['property_name']?>;
    <?endforeach?>

}