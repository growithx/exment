<?php

namespace Exceedone\Exment\Controllers;

use Encore\Admin\Form;
use Encore\Admin\Widgets\Form as WidgetForm;
use Encore\Admin\Widgets\Box;
use Exceedone\Exment\Form\Widgets\ModalForm;
use Encore\Admin\Grid;
use Encore\Admin\Grid\Linker;
use Encore\Admin\Layout\Content;
use Illuminate\Http\Request;
use Exceedone\Exment\Model\CustomTable;
use Exceedone\Exment\Model\Workflow;
use Exceedone\Exment\Model\WorkflowAction;
use Exceedone\Exment\Model\WorkflowStatus;
use Exceedone\Exment\Model\WorkflowTable;
use Exceedone\Exment\Model\Condition;
use Exceedone\Exment\Model\CustomViewFilter;
use Exceedone\Exment\Enums\Permission;
use Exceedone\Exment\Enums\SystemTableName;
use Exceedone\Exment\Enums\ColumnType;
use Exceedone\Exment\Enums\WorkflowType;
use Exceedone\Exment\Enums\WorkflowTargetSystem;
use Exceedone\Exment\Enums\WorkflowWorkTargetType;
use Exceedone\Exment\Enums\ConditionTypeDetail;
use Exceedone\Exment\Enums\ViewColumnFilterOption;
use Exceedone\Exment\Form\Tools\ConditionHasManyTable;
use Exceedone\Exment\Form\Tools\SwalInputButton;
use Exceedone\Exment\Form\Field\WorkFlow as WorkFlowField;
use Exceedone\Exment\Form\Field\ChangeField;
use Exceedone\Exment\Services\AuthUserOrgHelper;
use Exceedone\Exment\Validator\ChangeFieldRule;
use Symfony\Component\HttpFoundation\Response;
use \Carbon\Carbon;

class WorkflowController extends AdminControllerBase
{
    use HasResourceActions;

    protected $exists = false;

    public function __construct(Request $request)
    {
        $this->setPageInfo(exmtrans("workflow.header"), exmtrans("workflow.header"), exmtrans("workflow.description"), 'fa-share-alt');
    }

    /**
     * Make a grid builder.
     *
     * @return Grid
     */
    protected function grid()
    {
        $grid = new Grid(new Workflow);
        $grid->column('id', exmtrans("common.id"));
        $grid->column('workflow_type', exmtrans("workflow.workflow_type"))->display(function($v){
            return WorkflowType::getEnum($v)->transKey('workflow.workflow_type_options');
        });
        $grid->column('workflow_tables', exmtrans("custom_table.table"))->display(function($v){
            if(is_null($custom_table = $this->getDesignatedTable())){
                return null;
            }

            return $custom_table->table_view_name;
        });
        $grid->column('workflow_view_name', exmtrans("workflow.workflow_view_name"))->sortable();
        $grid->column('workflow_statuses', exmtrans("workflow.status_name"))->display(function($value){
            return $this->getStatusesString();
        });
        $grid->column('setting_completed_flg', exmtrans("workflow.setting_completed_flg"))->display(function($value){
            if(boolval($value)){
                return getTrueMark($value);
            }

            return null;
        });
        
        $grid->disableExport();
        if (!\Exment::user()->hasPermission(Permission::SYSTEM)) {
            $grid->disableCreateButton();
        }

        $grid->actions(function (Grid\Displayers\Actions $actions) {
            $actions->disableView();

            // add new edit link
            $linker = (new Linker)
                ->url(admin_urls('workflow', $actions->getKey(), 'edit?action=2'))
                ->icon('fa-exchange')
                ->tooltip(exmtrans('workflow.action'));
            $actions->prepend($linker);
            
            if ($actions->row->disabled_delete) {
                $actions->disableDelete();
            }
        });

        $grid->tools(function ($tools) {
            
            if(Workflow::hasSettingCompleted()){
                $tools->append(view('exment::tools.button', [
                    'href' => admin_url('workflow/beginning'),
                    'label' => exmtrans('workflow.beginning'),
                    'icon' => 'fa-cog',
                    'btn_class' => 'btn-primary',
                ]));
            }
        });

        return $grid;
    }

    /**
     * Make a form builder.
     *
     * @return Form
     */
    protected function form($id = null)
    {
        if(!isset($id)){
            return $this->statusForm();
        }

        // get request
        $action = request()->get('action', 1);

        switch($action){
            case 2:
                return $this->actionForm($id);
            case 3:
                return $this->beginningForm($id);
            default:
                return $this->statusForm($id);
        }
    }

    /**
     * Make a action edit form builder.
     *
     * @return Form
     */
    public function action(Request $request, Content $content, $id)
    {
        return $this->AdminContent($content)->body($this->actionForm($id)->edit($id));
    }

    /**
     * Make a action edit form builder.
     *
     * @return Form
     */
    protected function statusForm($id = null)
    {
        $workflow = Workflow::getEloquentDefault($id);

        $form = new Form(new Workflow);
        $form->progressTracker()->options($this->getProgressInfo($workflow, 1));
        $form->text('workflow_view_name', exmtrans("workflow.workflow_view_name"))
            ->required()
            ->rules("max:40");

        // is create
        if(!isset($workflow)){
            $form->select('workflow_type', exmtrans('workflow.workflow_type'))
                ->options(WorkflowType::transKeyArray('workflow.workflow_type_options'))
                ->attribute(['data-filtertrigger' =>true])
                ->config('allowClear', false)
                ->help(exmtrans('common.help.init_flg') . exmtrans('workflow.help.workflow_type'))
                ->required();
                
            $form->select('custom_table_id', exmtrans('custom_table.table'))->options(function ($value) {
                return CustomTable::allRecords(function ($custom_table) {
                    return !in_array($custom_table->table_name, SystemTableName::SYSTEM_TABLE_NAME_MASTER())
                && !in_array($custom_table->table_name, SystemTableName::SYSTEM_TABLE_NAME_IGNORE_SAVED_AUTHORITY());
                })->pluck('table_view_name', 'id')->toArray();
            })->required()
            ->attribute(['data-filter' => json_encode(['key' => 'workflow_type', 'value' => [WorkflowType::TABLE]])])
            ;
            $form->ignore('custom_table_id');
        }
        // is update
        else{
            $form->display('workflow_type', exmtrans('workflow.workflow_type'))
                ->displayText(WorkflowType::getEnum($workflow->workflow_type)->transKey('workflow.workflow_type_options'))
                ;

            if($workflow == WorkflowType::TABLE){
                $form->display('custom_table_id', exmtrans('custom_table.table'));
            }
        }
        
        $form->text('start_status_name', exmtrans("workflow.start_status_name"))
            ->required()
            ->rules("max:30");

        $field = $form->hasManyTable('workflow_statuses', exmtrans("workflow.workflow_statuses"), function ($form) {
            $form->text('status_name', exmtrans("workflow.status_name"))->help(exmtrans('workflow.help.status_name'));
            $form->switchbool('datalock_flg', exmtrans("workflow.datalock_flg"))->help(exmtrans('workflow.help.editable_flg'));
            $form->hidden('order')->default(0);
        })->setTableWidth(8, 2)
        ->required()
        ->setTableColumnWidth(6, 2, 2);
        if(isset($workflow) && boolval($workflow->setting_completed_flg)){
            $field->disableDelete()
                ->disableCreate();
        }else{
            $field->rowUpDown('order')
                ->description(sprintf(exmtrans("workflow.description_workflow_statuses")));
        }
        
        $form->saving(function (Form $form) {
            $this->exists = $form->model()->exists;
        });

        $form->savedInTransaction(function (Form $form) use ($id) {
            $model = $form->model();

            // get workflow_statuses and set completed fig
            $statuses = $model->workflow_statuses()->orderby('order', 'desc')->get();

            foreach($statuses as $index => $status){
                $status->completed_flg = ($index === 0);
                $status->save();
            }

            // save table info
            if(request()->get('workflow_type') != WorkflowType::TABLE){
                return;
            }
            if(is_null($custom_table_id = request()->get('custom_table_id'))){
                return;
            }

            WorkflowTable::create([
                'custom_table_id' => $custom_table_id,
                'workflow_id' => $model->id,
            ]);
        });

        $self = $this;
        $form->tools(function (Form\Tools $tools) use($self, $workflow) {
            $self->appendActivateButton($workflow, $tools);
            $self->appendTableSettingButton($workflow, $tools);
            $self->disableDelete($workflow, $tools);
        });

        $form->saved(function (Form $form) use ($id) {
            $model = $form->model();

            // redirect workflow action page
            if (!$this->exists) {
                $workflow_action_url = admin_urls('workflow', $model->id, 'edit?action=2');
    
                admin_toastr(exmtrans('workflow.help.saved_redirect_column'));
                return redirect($workflow_action_url);
            }
        });

        return $form;
    }

    /**
     * Make a action edit form builder.
     *
     * @return Form
     */
    protected function actionForm($id)
    {
        $workflow = Workflow::getEloquentDefault($id);

        $form = new Form(new Workflow);
        $form->progressTracker()->options($this->getProgressInfo($workflow, 2));
        $form->hidden('action')->default(2);
        $form->display('workflow_view_name', exmtrans("workflow.workflow_view_name"));
        $form->display('workflow_status', exmtrans("workflow.status_name"))
            ->default($workflow->getStatusesString());

        $field = $form->hasManyTable('workflow_actions', exmtrans("workflow.workflow_actions"), function($form) use($id, $workflow){
            $form->workflowStatusSelects('status_from', exmtrans("workflow.status_name"))
                ->config('allowClear', false)
                ->options(function($value, $field){
                    return $this->getStatusOptions($field->getIndex() === 0);
                });

            $form->valueModal('work_conditions', exmtrans("workflow.work_conditions"))
                ->ajax(admin_urls('workflow', $id, 'modal', 'condition'))
                ->modalContentname('workflow_actions_work_conditions')
                ->setElementClass('workflow_actions_work_conditions')
                ->buttonClass('btn-sm btn-default')
                ->valueTextScript('Exment.WorkflowEvent.GetConditionSettingValText();')
                ->hiddenFormat(function($value){
                    if(is_nullorempty($value)){
                        return null;
                    }

                    $value = Condition::getWorkConditions($value);

                    return collect($value)->toJson();
                })
                ->text(function ($value, $field) use($workflow) {
                    if(is_nullorempty($value)){
                        return null;
                    }

                    $work_conditions = Condition::getWorkConditions($value);

                    // set text
                    $texts = [];
                    foreach($work_conditions as $work_condition){
                        if(!boolval(array_get($work_condition, 'enabled_flg'))){
                            continue;
                        }
                        $texts[] = WorkflowStatus::getWorkflowStatusName(array_get($work_condition, "status_to"), $workflow);
                    }
                    return $texts;
                })
                ->nullText(exmtrans("common.no_setting"))
            ;

            $form->valueModal('work_targets', exmtrans("workflow.work_targets"))
                ->ajax(admin_urls('workflow', $id, 'modal', 'target'))
                ->modalContentname('workflow_actions_work_targets')
                ->setElementClass('workflow_actions_work_targets')
                ->buttonClass('btn-sm btn-default')
                ->valueTextScript('Exment.WorkflowEvent.GetSettingValText();')
                ->hiddenFormat(function($value, $field){
                    if(is_nullorempty($value)){
                        return WorkflowWorkTargetType::getTargetTypeDefault($field->getIndex());
                    }

                    $value = jsonToArray($value);

                    // if is not vector array(as callback error response)
                    if(!is_vector($value)){
                        $result = $value;
                    }else{
                        $result = [];
                        collect($value)->each(function($v) use(&$result){
                            $result[array_get($v, 'related_type')][] = array_get($v, 'related_id');
                        });
                    }

                    $result['work_target_type'] = array_get($field->data(), 'options.work_target_type');

                    return collect($result)->toJson();
                })
                ->text(function ($value, $field) {
                    if(is_nullorempty($value)){
                        return WorkflowWorkTargetType::getTargetTypeNameDefault($field->getIndex());
                    }

                    $action = WorkflowAction::getEloquentDefault($field->data()['id']);
                    if(!isset($action)){
                        return WorkflowWorkTargetType::getTargetTypeNameDefault($field->getIndex());
                    }
                    
                    return $action->getAuthorityTargets(null, false, true);
                })
                ->nullText(exmtrans("common.created_user"))
                ->nullValue(function($value, $field){
                    return WorkflowWorkTargetType::getTargetTypeDefault($field->getIndex());
                })
            ;

            $form->workflowOptions('options', exmtrans("workflow.option"));
        })->setTableColumnWidth(3, 2, 3, 3, 1)
           ->setRelatedValue([[]])
           ->required()
           ->hideDeleteButtonRow(1);

        $self = $this;
        $form->tools(function (Form\Tools $tools) use($self, $workflow) {
            $self->appendActivateButton($workflow, $tools);
            $self->appendTableSettingButton($workflow, $tools);
            $self->disableDelete($workflow, $tools);
        });

        $form->ignore(['action']);

        $form->saving(function (Form $form) {
            $result = $this->validateData($form);
            if($result instanceof Response){
                return $result; 
            }
        });

        return $form;
    }

    /**
     * Make a beginning form builder.
     *
     * @return Form
     */
    protected function beginningForm()
    {
        $content = new Content;
        $this->AdminContent($content);

        $form = new WidgetForm();
        $form->disablereset();
        $form->action(admin_urls('workflow', 'beginning'));

        $results = [];

        if (is_null($results = old('workflow_tables'))) {
            $workflowTables = WorkflowTable::with(['workflow', 'custom_table'])->get()
            ->filter(function ($workflowTable) {
                if (!boolval($workflowTable->workflow->setting_completed_flg)) {
                    return false;
                }

                return true;
            });

            // get all "common" and settinged workflows
            $workflowCommons = Workflow::allRecords(function ($workflow) {
                if ($workflow->workflow_type != WorkflowType::COMMON) {
                    return false;
                }

                if (!boolval($workflow->setting_completed_flg)) {
                    return false;
                }

                return true;
            });

            // get all custom tables
            $custom_tables = CustomTable::allRecords(function ($custom_table) {
                return !in_array($custom_table->table_name, SystemTableName::SYSTEM_TABLE_NAME_MASTER())
            && !in_array($custom_table->table_name, SystemTableName::SYSTEM_TABLE_NAME_IGNORE_SAVED_AUTHORITY());
            });

            foreach ($custom_tables as $custom_table) {
                $results[$custom_table->id] = [
                'custom_table' => $custom_table,
                'workflows' => []
            ];

                // append already setting workflow table
                $workflowTables->filter(function ($workflowTable) use ($custom_table) {
                    if ($custom_table->id !== $workflowTable->custom_table->id) {
                        return false;
                    }

                    return true;
                })->each(function ($workflowTable) use (&$results, $custom_table) {
                    $workflow = $workflowTable->workflow;
                    $results[$custom_table->id]['workflows'][$workflow->id] = [
                    'workflow_view_name' => $workflow->workflow_view_name,
                    'active_start_date' => $workflowTable->active_start_date,
                    'active_end_date' => $workflowTable->active_end_date,
                    'active_flg' => $workflowTable->active_flg,
                ];
                });

                // append common workflows
                $workflowCommons->each(function ($workflow) use (&$results, $custom_table) {
                    if (array_has($results[$custom_table->id]['workflows'], $workflow->id)) {
                        return;
                    }

                    $results[$custom_table->id]['workflows'][$workflow->id] = [
                    'workflow_view_name' => $workflow->workflow_view_name,
                ];
                    ;
                });
            }
        }

        // add form
        $form->description('各テーブルごとに、使用するワークフローを1件まで選択します。<br />※使用するワークフローを変更した場合でも、現在進行中のワークフローは、変更前のワークフローで実行されます。<br />' . exmtrans('workflow.help.workflow_help'))
            ;

        $form->html(view('exment::workflow.beginning', [
            'items' => $results
        ])->render());

        $box = new Box(exmtrans('workflow.beginning'), $form);
        $box->tools(view('exment::tools.button', [
            'href' => admin_url('workflow'),
            'label' => trans('admin.list'),
            'icon' => 'fa-list',
        ])->render());

        $content->row($box);
        return $content;
    }

    /**
     * save beginning info
     *
     * @return Form
     */
    protected function beginningPost(Request $request)
    {
        $workflow_tables = $request->get('workflow_tables');

        //workflow validation
        $vavidator = \Validator::make($request->all(), [
            'workflow_tables.*.workflows.*.active_start_date' => ['nullable', 'date', 'before_or_equal:workflow_tables.*.workflows.*.active_end_date'],
            'workflow_tables.*.workflows.*.active_end_date' => ['nullable', 'date']
        ]);

        $errors = $vavidator->errors();

        foreach ($workflow_tables as $custom_table_id => $item) {
            // get active_flg's count
            $workflows = array_get($item, 'workflows', []);
            if(collect($workflows)->filter(function($workflow_item){
                return boolval(array_get($workflow_item, 'active_flg'));
            })->count() >= 2){
                // check date
                $searchDates = collect($workflows)->map(function($workflow_item){
                    return [
                        'start' => Carbon::parse(array_get($workflow_item, 'active_start_date') ?? '1900-01-01'),
                        'end' => Carbon::parse(array_get($workflow_item, 'active_end_date') ?? '9999-12-31'),
                    ];
                });

                if(hasDuplicateDate($searchDates)){
                    $errors->add("workflow_tables.$custom_table_id.custom_table", 'ワークフローが重複しています。');
                }
            }
        }

        if (count($errors->getMessages()) > 0) {
            return back()->withErrors($errors)
                        ->withInput();
        }

        \DB::transaction(function() use($workflow_tables){
            foreach($workflow_tables as $custom_table_id => $item){
                foreach(array_get($item, 'workflows', []) as $workflow_id => $workflow_item){
                    // get workflow table using custom table id and workflow id
                    $workflow_table = WorkflowTable::firstOrNew(['custom_table_id' => $custom_table_id, 'workflow_id' => $workflow_id]);
    
                    // if active, set each parameters
                    if(boolval(array_get($workflow_item, 'active_flg'))){
                        $workflow_table->active_flg = true;
                        $workflow_table->active_start_date = array_get($workflow_item, 'active_start_date');
                        $workflow_table->active_end_date = array_get($workflow_item, 'active_end_date');
                    }
                    // not active, reset
                    else{
                        $workflow_table->active_flg = false;
                        $workflow_table->active_start_date = null;
                        $workflow_table->active_end_date = null;
                    }

                    $workflow_table->save();
                }
            }
        });
        
        admin_toastr(trans('admin.save_succeeded'));
        return back();
    }

    public function appendActivateButton($workflow, $tools){
        if(isset($workflow) && $workflow->canActivate()){
            $tools->append(new SwalInputButton(
                [
                    'title' => '設定完了する',
                    'label' => '設定完了する',
                    'confirmKeyword' => 'yes',
                    'icon' => 'fa-check-circle-o',
                    'html' => 'このワークフローの設定を完了します。設定完了すると、以下の内容が実施できなくなります。<br />・ワークフローの削除<br />・ステータスの追加、削除、順番変更<br />よろしければ、「yes」と入力してください。',
                    'url' => admin_urls('workflow', $workflow->id, 'activate'),
                ]
            ));
        }
    }

    /**
     * Append table setting button
     */
    public function appendTableSettingButton($workflow, $tools){
        if(isset($workflow) && boolval($workflow->setting_completed_flg)){
            $tools->append(view('exment::tools.button', [
                'href' => admin_url('workflow/beginning'),
                'label' => exmtrans('workflow.beginning'),
                'icon' => 'fa-cog',
                'btn_class' => 'btn-primary',
            ]));
        }
    }

    public function disableDelete($workflow, $tools){
        if(isset($workflow) && $workflow->disabled_delete){
            $tools->disableDelete();
        }
    }
    /**
     * Activate workflow
     *
     * @param Request $request
     * @param [type] $id
     * @return void
     */
    public function activate(Request $request, $id){
        $workflow = Workflow::getEloquentDefault($id);
        if(!$workflow->canActivate()){
            // TODO:workflow already activate
            return back();
        }

        $workflow->setting_completed_flg = true;
        $workflow->save();

        return response()->json([
            'result'  => true,
            'toastr' => trans('admin.save_succeeded'),
            'redirect' => admin_url('workflow/beginning'),
        ]);
    }

    /**
     * validate before save.
     */
    protected function validateData(Form $form)
    {
        $request = request();

        $data = $request->all();

        // simple validation
        $keys = collect([
            "action_name" => 'required|max:30',
            "status_from" => 'required',
            "work_conditions" => 'required',
            "work_targets" => 'required',
            "flow_next_type" => 'required',
            "flow_next_count" => 'required|numeric|min:0|max:10',
            "comment_type" => 'required',
        ]);
        $validation = $keys->mapWithKeys(function($v, $k){
            return ["workflow_actions.*.$k" => $v];
        })->toArray();

        $attributes = $keys->mapWithKeys(function($v, $k){
            return ["workflow_actions.*.$k" => exmtrans("workflow.$k")];
        })->toArray();

        $validator = \Validator::make($data, $validation, [], $attributes);
        $errors = $validator->errors();

        // especially validation
        foreach(array_get($data, 'workflow_actions', []) as $key => $workflow_action){
            $errorKey = "workflow_actions.$key";

            // get action conditions
            $workflow_conditions = Condition::getWorkConditions(array_get($workflow_action, 'work_conditions'));
            
            foreach($workflow_conditions as $workflow_condition){
                if(array_get($workflow_condition, 'status_to') == array_get($workflow_action, 'status_from')){
                    $errors->add("$errorKey.status_from", '実行前ステータスと実行後ステータスは、異なるステータスに設定してください。');
                    break;
                }
            }

        }

        if (count($errors->getMessages()) > 0) {
            return back()->withErrors($errors)
                        ->withInput();
        }
    }

    protected function getProgressInfo($workflow, $action) {
        $id = $workflow->id ?? null;

        $steps = [];
        $hasAction = false;
        $workflow_action_url = null;
        $workflow_status_url = null;
        if (isset($id)) {
            $hasAction = WorkflowAction::where('workflow_id', $id)->count() > 0;
            $workflow_action_url = admin_urls('workflow', $id, 'edit?action=2');
            $workflow_status_url = admin_urls('workflow', $id, 'edit');
        }
        
        $steps[] = [
            'active' => ($action == 1),
            'complete' => false,
            'url' => ($action != 1)? $workflow_status_url: null,
            'description' => exmtrans('workflow.workflow_statuses')
        ];

        $steps[] = [
            'active' => ($action == 2),
            'complete' => false,
            'url' => ($action != 2)? $workflow_action_url: null,
            'description' => exmtrans('workflow.workflow_actions')
        ];

        if (isset($workflow) && boolval($workflow->setting_completed_flg)) {
            $steps[] = [
                'active' => ($action == 3),
                'complete' => false,
                'url' => ($action != 3) ? admin_url('workflow/beginning') : null,
                'description' => exmtrans('workflow.beginning'),
            ];
        }
        
        return $steps;
    }

    /**
     * Get target modal html
     *
     * @param Request $request
     * @param [type] $id
     * @return void
     */
    public function targetModal(Request $request, $id){
        $workflow = Workflow::getEloquentDefault($id);
        $custom_table = $workflow->getDesignatedTable();

        // get selected value
        $value = $request->get('workflow_actions_work_targets');
        $value = jsonToArray($value);

        $index = $request->get('index');

        $form = AuthUserOrgHelper::getUserOrgModalForm($custom_table, $value, [
            'prependCallback' => function($form) use($workflow, $value, $index){
                if($index > 0){
                    $options = [
                        WorkflowWorkTargetType::ACTION_SELECT => WorkflowWorkTargetType::ACTION_SELECT()->transKey('workflow.work_target_type_options'), 
                        WorkflowWorkTargetType::FIX => WorkflowWorkTargetType::FIX()->transKey('workflow.work_target_type_options')
                    ];
                    $help = exmtrans('workflow.help.work_target_type2');
                    $default = WorkflowWorkTargetType::ACTION_SELECT;
                    $form->radio('work_target_type', exmtrans('workflow.work_target_type'))
                        ->help($help)
                        ->attribute(['data-filtertrigger' =>true])
                        ->default(array_get($value, 'work_target_type', $default))
                        ->options($options);
                }else{
                    $form->hidden('work_target_type')->default(WorkflowWorkTargetType::FIX);
                }
            }
        ]);

        // set custom column
        if(isset($custom_table)){
            $options = $custom_table->custom_columns
                ->whereIn('column_type', [ColumnType::USER, ColumnType::ORGANIZATION])
                ->pluck('column_view_name', 'id');
            $form->multipleSelect(ConditionTypeDetail::SYSTEM, exmtrans('common.custom_column'))
                ->options($options)
                ->setElementClass('modal_' . ConditionTypeDetail::COLUMN)
                ->attribute(['data-filter' => json_encode(['key' => 'work_target_type', 'value' => 'fix'])])
                ->default(array_get($value, ConditionTypeDetail::COLUMN));
        }

        // set workflow system column
        $modal_system_default = array_get($value, SystemTableName::SYSTEM);
        if (!isset($modal_system_default)) {
            $modal_system_default = ($index == 0 ? [WorkflowTargetSystem::CREATED_USER] : null);
        }
        $form->multipleSelect(ConditionTypeDetail::SYSTEM, exmtrans('common.system'))
            ->options(WorkflowTargetSystem::transKeyArray('common'))
            ->setElementClass('modal_' . ConditionTypeDetail::SYSTEM)
            ->attribute(['data-filter' => json_encode(['key' => 'work_target_type', 'value' => 'fix'])])
            ->default($modal_system_default);

        $form->hidden('valueModalUuid')->default($request->get('widgetmodal_uuid'));

        $form->setWidth(9, 2);

        return getAjaxResponse([
            'body'  => $form->render(),
            'script' => $form->getScript(),
            'title' => exmtrans('workflow.work_targets'),
            'showReset' => true,
            'submitlabel' => trans('admin.setting'),
            'contentname' => 'workflow_actions_work_targets',
        ]);
    }

    /**
     * Get condition modal html
     *
     * @param Request $request
     * @param [type] $id
     * @return void
     */
    public function conditionModal(Request $request, $id){
        $workflow = Workflow::find($id);
        $custom_table = $workflow->getDesignatedTable();
        $statusOptions = $workflow->getStatusOptions();
        $workflow_type = WorkflowType::getEnum($workflow->workflow_type);

        // get selected value
        $value = $request->get('workflow_actions_work_conditions');
        $value = Condition::getWorkConditions($value);

        $form = new ModalForm($value);

        if(isset($workflow_type)){
            $form->description(exmtrans('workflow.help.work_conditions_' . $workflow_type->lowerKey()))
                ->setWidth(10, 2);
        }

        // set range.
        $range = ($workflow_type == WorkflowType::COMMON) ? range(0, 0) : range(0, 2); 
        foreach($range as $index){
            $work_condition = array_get($value, $index, []);
            if($workflow_type == WorkflowType::TABLE){
                $label = exmtrans('workflow.condition') .  ($index + 1);
                $form->exmheader($label)
                    ->hr();
            }

            if($index === 0){
                $form->hidden("enabled_flg_{$index}")
                ->default(1);
            }else{
                $form->checkboxone("enabled_flg_{$index}", 'enabled')
                ->setLabelClass(['invisible'])
                ->setWidth(10, 2)
                ->setElementClass('work_conditions_enabled')
                ->default(array_get($work_condition, 'enabled_flg', 0))
                ->attribute(['data-filtertrigger' =>true])
                ->option(['1' => exmtrans('custom_form.available')]);
            }
            
            $form->select("status_to_{$index}", exmtrans('workflow.status_to'))
                ->options($statusOptions)
                ->required()
                ->default(array_get($work_condition, "status_to"))
                ->setElementClass('work_conditions_status_to')
                ->attribute(['data-filter' => json_encode(['key' => "enabled_flg_{$index}", 'value' => '1'])])
                ->setWidth(4, 2);

            if(isset($custom_table)){
                $default = array_get($work_condition, "workflow_conditions", []);
                
                // filter setting
                $hasManyTable = new ConditionHasManyTable($form, [
                    'ajax' => admin_url("webapi/{$id}/filter-value"),
                    'name' => "workflow_conditions_{$index}",
                    'linkage' => json_encode(['condition_key' => admin_urls('webapi', $custom_table->table_name, 'filter-condition')]),
                    'targetOptions' => $custom_table->getColumnsSelectOptions([
                        'include_system' => false,
                    ]),
                    'custom_table' => $custom_table,
                ]);

                $hasManyTable->callbackField(function($field) use($default, $index){
                    $field->setRelatedValue($default)
                        ->attribute(['data-filter' => json_encode(['key' => "enabled_flg_{$index}", 'value' => '1'])])
                    ;
                });

                $hasManyTable->render();
                
                // $form->hasManyTable("filter_{$index}", exmtrans("custom_view.custom_view_filters"), function ($form) use ($custom_table, $id) {
                //     $form->select('view_column_target', exmtrans("custom_view.view_column_target"))->required()
                //         ->options($custom_table->getColumnsSelectOptions(
                //             [
                //             ]
                //         ))
                //         ->attribute([
                //             'data-linkage' => json_encode(['view_filter_condition' => admin_urls('view', $custom_table->table_name, 'filter-condition')]),
                //             'data-change_field_target' => 'view_column_target',
                //         ]);
        
                //     $form->select('view_filter_condition', exmtrans("custom_view.view_filter_condition"))->required()
                //         ->options(function ($val, $select) {
                //             // if null, return empty array.
                //             if (!isset($val)) {
                //                 return [];
                //             }
        
                //             $data = $select->data();
                //             $view_column_target = array_get($data, 'view_column_target');
        
                //             // get column item
                //             $column_item = CustomViewFilter::getColumnItem($view_column_target);
        
                //             ///// get column_type
                //             $column_type = $column_item->getViewFilterType();
        
                //             // if null, return []
                //             if (!isset($column_type)) {
                //                 return [];
                //             }
        
                //             // get target array
                //             $options = array_get(ViewColumnFilterOption::VIEW_COLUMN_FILTER_OPTIONS(), $column_type);
                //             return collect($options)->mapWithKeys(function ($array) {
                //                 return [$array['id'] => exmtrans('custom_view.filter_condition_options.'.$array['name'])];
                //             });
        
                //             return [];
                //         });
                //     $label = exmtrans('custom_view.view_filter_condition_value_text');
                //     $form->changeField('view_filter_condition_value', $label)
                //         ->ajax(admin_url("workflow/{$id}/filter-value"))
                //         ->setEventTrigger('.view_filter_condition')
                //         ->setEventTarget('select.view_column_target')
                //         ->rules([new ChangeFieldRule(null, $label)]);
                // })->setTableColumnWidth(4, 4, 3, 1)
                // ->setTableWidth(10, 2)
                // ->setElementClass('work_conditions_filter')
                // ->setRelatedValue($default)
                // ->attribute(['data-filter' => json_encode(['key' => "enabled_{$index}", 'value' => '1'])])
                // ->disableHeader();
            }
        }

        $form->hidden('valueModalUuid')->default($request->get('widgetmodal_uuid'));

        return getAjaxResponse([
            'body'  => $form->render(),
            'script' => $form->getScript(),
            'title' => exmtrans("workflow.work_conditions"),
            'showReset' => true,
            'submitlabel' => trans('admin.setting'),
            'contentname' => 'workflow_actions_work_conditions',
        ]);
    }
}
