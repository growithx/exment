<?php

namespace Exceedone\Exment\ChangeFieldItems;

use Encore\Admin\Form\Field;
use Exceedone\Exment\Model\CustomTable;
use Exceedone\Exment\Enums\SystemTableName;
use Exceedone\Exment\Enums\ConditionType;
use Exceedone\Exment\Enums\ViewColumnFilterOption;
use Exceedone\Exment\Enums\ViewColumnFilterType;

class UserItem extends ChangeFieldItem
{
    public function getFilterOption(){
        return array_get(ViewColumnFilterOption::VIEW_COLUMN_FILTER_OPTIONS(), ViewColumnFilterType::SELECT);
    }
    
    /**
     * Get change field
     *
     * @param [type] $target_val
     * @param [type] $key
     * @return void
     */
    public function getChangeField($key){
        $options = CustomTable::getEloquent(SystemTableName::USER)->getSelectOptions([
            'display_table' => $this->custom_table
        ]);
        $field = new Field\MultipleSelect($this->elementName, [$this->label]);
        return $field->options($options);
    }
}
