<?php

namespace Exceedone\Exment\Database\View;

use Exceedone\Exment\Enums\SystemTableName;
use Exceedone\Exment\Model\Define;

class WorkflowValueView
{
    /**
     * create workflow view sql
     */
    public static function createWorkflowValueView()
    {
        /////// first query. not has workflow value's custom value
        $subquery = \DB::table(SystemTableName::WORKFLOW_TABLE)
        ->join(SystemTableName::WORKFLOW, function ($join) {
            $join->on(SystemTableName::WORKFLOW_TABLE . '.workflow_id', SystemTableName::WORKFLOW . ".id")
                ;
        })
        ->join(SystemTableName::WORKFLOW_ACTION, function ($join) {
            $join->on(SystemTableName::WORKFLOW_ACTION . '.workflow_id', SystemTableName::WORKFLOW . ".id")
                ->where(SystemTableName::WORKFLOW_ACTION . '.status_from', Define::WORKFLOW_START_KEYNAME)
                ;
        })
        ->join(SystemTableName::WORKFLOW_AUTHORITY, function ($join) {
            $join->on(SystemTableName::WORKFLOW_AUTHORITY . '.workflow_action_id', SystemTableName::WORKFLOW_ACTION . ".id")
                ;
        })
        ->where(SystemTableName::WORKFLOW_TABLE . '.active_flg', 1)
        ->distinct()
        ->select([
            \DB::raw('1 as workflow_view_type'),
            \DB::raw('null as workflow_value_id'),
            'workflows.id as workflow_id',
            'workflow_tables.custom_table_id as workflow_table_id',
            \DB::raw('null as custom_value_id'),
            \DB::raw('null as custom_value_type'),
            'workflow_actions.id as workflow_action_id',
            'workflow_authorities.related_id as authority_related_id',
            'workflow_authorities.related_type as authority_related_type',
            \DB::raw('null as status_to_id'),
        ]);




        /////// second query. has workflow value's custom value

        
        $subquery2 = \DB::table(SystemTableName::WORKFLOW_TABLE)
        ->join(SystemTableName::WORKFLOW, function ($join) {
            $join->on(SystemTableName::WORKFLOW_TABLE . '.workflow_id', SystemTableName::WORKFLOW . ".id")
                ;
        })
        ->join(SystemTableName::CUSTOM_TABLE, function ($join) {
            $join->on(SystemTableName::WORKFLOW_TABLE . '.custom_table_id', SystemTableName::CUSTOM_TABLE . ".id")
                ;
        })
        ->join(SystemTableName::WORKFLOW_VALUE, function ($join) {
            $join->on(SystemTableName::WORKFLOW_VALUE . '.morph_type', SystemTableName::CUSTOM_TABLE . ".table_name")
                ->on(SystemTableName::WORKFLOW_VALUE . '.workflow_id', SystemTableName::WORKFLOW . ".id")
                ;
        })
        ->join(SystemTableName::WORKFLOW_ACTION, function ($join) {
            $join
            ->on(SystemTableName::WORKFLOW_ACTION . '.workflow_id', SystemTableName::WORKFLOW . ".id")
            ->where('ignore_work', 0)
            ->where(function ($query) {
                $query->where(function ($query) {
                    $query->where(SystemTableName::WORKFLOW_ACTION . '.status_from', Define::WORKFLOW_START_KEYNAME)
                        ->whereNull(SystemTableName::WORKFLOW_VALUE . '.workflow_status_to_id')
                    ;
                })->orWhere(function ($query) {
                    $query->whereColumn(SystemTableName::WORKFLOW_ACTION . '.status_from', SystemTableName::WORKFLOW_VALUE . '.workflow_status_to_id');
                });
            });
        })
        ->join(SystemTableName::WORKFLOW_AUTHORITY, function ($join) {
            $join->on(SystemTableName::WORKFLOW_AUTHORITY . '.workflow_action_id', SystemTableName::WORKFLOW_ACTION . ".id")
                ;
        })
        ->where(SystemTableName::WORKFLOW_VALUE . '.latest_flg', 1)
        ->where(SystemTableName::WORKFLOW_TABLE . '.active_flg', 1)
        ->distinct()
        ->select([
            \DB::raw('2 as workflow_view_type'),
            'workflow_values.id as workflow_value_id',
            'workflows.id as workflow_id',
            'workflow_tables.custom_table_id as workflow_table_id',
            'workflow_values.morph_id as custom_value_id',
            'workflow_values.morph_type as custom_value_type',
            'workflow_actions.id as workflow_action_id',
            'workflow_authorities.related_id as authority_related_id',
            'workflow_authorities.related_type as authority_related_type',
            'workflow_values.workflow_status_to_id as status_to_id',
        ]);


        /////// third query. has workflow value's custom value and workflow value authorities

        $subquery3 = \DB::table(SystemTableName::WORKFLOW_TABLE)
        ->join(SystemTableName::WORKFLOW, function ($join) {
            $join->on(SystemTableName::WORKFLOW_TABLE . '.workflow_id', SystemTableName::WORKFLOW . ".id")
                ;
        })
        ->join(SystemTableName::CUSTOM_TABLE, function ($join) {
            $join->on(SystemTableName::WORKFLOW_TABLE . '.custom_table_id', SystemTableName::CUSTOM_TABLE . ".id")
                ;
        })
        ->join(SystemTableName::WORKFLOW_VALUE, function ($join) {
            $join->on(SystemTableName::WORKFLOW_VALUE . '.morph_type', SystemTableName::CUSTOM_TABLE . ".table_name")
                ->on(SystemTableName::WORKFLOW_VALUE . '.workflow_id', SystemTableName::WORKFLOW . ".id")
                ;
        })
        ->join(SystemTableName::WORKFLOW_ACTION, function ($join) {
            $join
            ->on(SystemTableName::WORKFLOW_ACTION . '.workflow_id', SystemTableName::WORKFLOW . ".id")
            ->where('ignore_work', 0)
            ->where(function ($query) {
                $query->where(function ($query)  {
                    $query->where(SystemTableName::WORKFLOW_ACTION . '.status_from', Define::WORKFLOW_START_KEYNAME)
                        ->whereNull(SystemTableName::WORKFLOW_VALUE . '.workflow_status_to_id')
                    ;
                })->orWhere(function ($query) {
                    $query->whereColumn(SystemTableName::WORKFLOW_ACTION . '.status_from', SystemTableName::WORKFLOW_VALUE . '.workflow_status_to_id');
                });
            });
        })
        ->join(SystemTableName::WORKFLOW_VALUE_AUTHORITY, function ($join) {
            $join->on(SystemTableName::WORKFLOW_VALUE_AUTHORITY . '.workflow_value_id', SystemTableName::WORKFLOW_VALUE . ".id")
                ;
        })
        ->where(SystemTableName::WORKFLOW_VALUE . '.latest_flg', 1)
        ->where(SystemTableName::WORKFLOW_TABLE . '.active_flg', 1)
        ->distinct()
        ->select([
            \DB::raw('3 as workflow_view_type'),
            'workflow_values.id as workflow_value_id',
            'workflows.id as workflow_id',
            'workflow_tables.custom_table_id as workflow_table_id',
            'workflow_values.morph_id as custom_value_id',
            'workflow_values.morph_type as custom_value_type',
            'workflow_actions.id as workflow_action_id',
            'workflow_value_authorities.related_id as authority_related_id',
            'workflow_value_authorities.related_type as authority_related_type',
            'workflow_values.workflow_status_to_id as status_to_id',
        ]);


        

        $subquery3->union($subquery)
            ->union($subquery2);
        
        return $subquery3;
    }
}
