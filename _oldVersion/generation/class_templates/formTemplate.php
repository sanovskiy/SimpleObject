<?='<?php'?>

<?$cd = $this->classData;
$conf = Zend_Registry::get('config');?> 
/**
 * <?=$cd['filename'] . 'EditForm.php'?> 
 *
 * edit form for table <?=$cd['table']?> 
 *
 * PHP version 5
 *
 * @author     <?=$conf->project->author?> 
 * @copyright  <?=$conf->project->copyright?> 
 *
 */
class Form_<?=$cd['class_name']?>Edit extends Base_Form_<?=$cd['class_name']?>Edit {

    public function __construct( )	{
        parent::__construct( );

        $this->addElements( $this->_fields );
        $this->removeDecorator('Form');     
    }

}