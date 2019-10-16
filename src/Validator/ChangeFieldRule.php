<?php
namespace Exceedone\Exment\Validator;

use Illuminate\Contracts\Validation\Rule;
use Exceedone\Exment\Model\CustomTable;
use Exceedone\Exment\ChangeFieldItems\ChangeFieldItem;

/**
 * Validation for change field
 */
class ChangeFieldRule implements Rule
{
    public function __construct(?CustomTable $custom_table, $label, $target)
    {
        $this->custom_table = $custom_table;
        $this->label = $label;
        $this->target = $target;
    }

    /**
    * Check Validation
    *
    * @param  string  $attribute
    * @param  mixed  $value
    * @return bool
    */
    public function passes($attribute, $value)
    {
//        $prefix = substr($attribute, 0, strrpos($attribute, '.'));

        $item = ChangeFieldItem::getItem($this->custom_table, $this->target);
        $field = getCustomField(array_get($this->data, $prefix), $field_label);

        if (!$validator = $field->getValidator([$field->column() => $value])) {
            return true;
        }

        if (($validator instanceof AdminValidator) && !$validator->passes()) {
            $parameters[] = $validator->messages->first();
            return false;
        }

        return true;

        return preg_match('/^[-]?[\d\s,\.]*$/', $value);
    }

    /**
     * get validation error message
     *
     * @return string
     */
    public function message()
    {
        return trans('validation.numeric');
    }
}
