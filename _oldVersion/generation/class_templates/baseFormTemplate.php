<?='<?php'?>

<?$cd = $this->classData;
$conf = Zend_Registry::get('config')?>
/**');
 * <?= $cd['filename'] . 'EditForm.php'?> 
 *
 * base class for edit form for table <?=$cd['table']?> 
 *
 * PHP version 5
 *
 * @author     <?=$conf->project->author?> 
 * @copyright  <?=$conf->project->copyright?> 
 */

class Base_Form_<?=$cd['class_name']?>Edit extends Shared_ZendFormFields {

    public function __construct( )	{
        parent::__construct( );
        <?foreach ($cd['fields'] as $field) :?>
<?     switch (strtolower($field['name'])) {
            default:
                break;
            case 'id':
            case 'editor':
            case 'creator':
            case 'date_edited':
            case 'date_created':
                continue 2;
            case 'password':
            case 'passwd':?>
        $this->stubPasswordChange('<?=$field['property_name']?>');
<?              continue 2;
            case 'email':
            case 'e-mail':
            case 'e_mail':?>
        $this->stubEmail('<?=$field['property_name']?>');
<?              continue 2;
        }
        
        switch (strtolower($field['field_type'])) {
            case 'timestamp':
            case 'datetime':?>
        $this->_fields['<?=$field['property_name']?>'] = $this->stubDatetimepicker('<?=$field['property_name']?>')->setLabel('<?=$field['property_name']?>');
<?              continue 2;
            case 'date':?>
        $this->_fields['<?=$field['property_name']?>'] = $this->stubDatepicker('<?=$field['property_name']?>')->setLabel('<?=$field['property_name']?>');
<?              $done = true;
                continue 2;
            case 'text':?>
        $this->_fields['<?=$field['property_name']?>'] = $this->stubElTextarea('<?=$field['property_name']?>')->setLabel('<?=$field['property_name']?>');
<?              continue 2;
        }

        if (strpos($field['name'], 'is_') === 0) :?>
        $this->_fields['<?=$field['property_name']?>'] = $this->stubElCheckbox('<?=$field['property_name']?>')->setLabel('<?=$field['property_name']?>');
<?        continue;
        endif;?>
        $this->_fields['<?=$field['property_name']?>'] = $this->stubElText('<?=$field['property_name']?>')->setLabel('<?=$field['property_name']?>');
<?
        endforeach;?>

    }
}