<?php

namespace Exceedone\Exment\ConditionItems;

use Encore\Admin\Form\Field;
use Exceedone\Exment\Model\CustomTable;
use Exceedone\Exment\Model\CustomValue;
use Exceedone\Exment\Model\Condition;
use Exceedone\Exment\Enums\FilterOption;
use Exceedone\Exment\Enums\SystemTableName;
use Exceedone\Exment\Enums\ConditionTypeDetail;

class UserItem extends ConditionItemBase implements ConditionItemInterface
{
    use UserOrganizationItemTrait;

    public function getFilterOption()
    {
        return $this->getFilterOptionConditon();
    }
    
    /**
     * Get change field
     *
     * @param [type] $target_val
     * @param [type] $key
     * @return \Encore\Admin\Form\Field
     */
    public function getChangeField($key, $show_condition_key = true)
    {
        return $this->getChangeFieldUserOrg(CustomTable::getEloquent(SystemTableName::USER), $key, $show_condition_key);
    }
    
    /**
     * check if custom_value and user(organization, role) match for conditions.
     *
     * @param CustomValue $custom_value
     * @return boolean
     */
    public function isMatchCondition(Condition $condition, CustomValue $custom_value)
    {
        $user = \Exment::getUserId();
        return $this->compareValue($condition, $user);
    }
    
    /**
     * get text.
     *
     * @param string $key
     * @param string $value
     * @param bool $showFilter
     * @return string
     */
    public function getText($key, $value, $showFilter = true)
    {
        $model = getModelName(SystemTableName::USER)::find($value);
        if ($model instanceof \Illuminate\Database\Eloquent\Collection) {
            $result = $model->filter()->map(function ($row) {
                return $row->getValue('user_name');
            })->implode(',');
        } else {
            if (!isset($model)) {
                return null;
            }

            $result = $model->getValue('user_name');
        }
        return $result . ($showFilter ? FilterOption::getConditionKeyText($key) : '');
    }
    
    /**
     * Check has workflow authority
     *
     * @param CustomValue $custom_value
     * @return boolean
     */
    public function hasAuthority($workflow_authority, $custom_value, $targetUser)
    {
        return $workflow_authority->related_id == $targetUser->id;
    }

    public static function setWorkflowConditionQuery($query, $tableName, $custom_table)
    {
        $query->orWhere(function ($query) {
            $query->where(SystemTableName::VIEW_WORKFLOW_VALUE . '.authority_related_id', \Exment::getUserId())
                ->where(SystemTableName::VIEW_WORKFLOW_VALUE . '.authority_related_type', ConditionTypeDetail::USER()->lowerkey());
        });
    }
}
