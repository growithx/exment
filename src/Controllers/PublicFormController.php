<?php

namespace Exceedone\Exment\Controllers;

use Illuminate\Routing\Controller;
use Encore\Admin\Facades\Admin;
use Encore\Admin\Form;
use Encore\Admin\Grid;
use Encore\Admin\Grid\Linker;
use Encore\Admin\Widgets\Form as WidgetForm;
use Encore\Admin\Widgets\Box;
use Encore\Admin\Layout\Content;
use Exceedone\Exment\Form\PublicContent;
use Exceedone\Exment\Model\CustomForm;
use Exceedone\Exment\Model\PublicForm;
use Exceedone\Exment\Model\CustomFormColumn;
use Exceedone\Exment\Model\CustomFormPriority;
use Exceedone\Exment\Model\CustomTable;
use Exceedone\Exment\Model\CustomValue;
use Exceedone\Exment\Model\Define;
use Exceedone\Exment\Model\CustomColumn;
use Exceedone\Exment\Model\File as ExmentFile;
use Exceedone\Exment\Form\Tools;
use Exceedone\Exment\Enums\FormLabelType;
use Exceedone\Exment\Enums\FileType;
use Exceedone\Exment\Enums\Permission;
use Exceedone\Exment\Enums\FormBlockType;
use Exceedone\Exment\Enums\FormColumnType;
use Exceedone\Exment\Services\FormSetting;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

/**
 * Custom Form Controller
 */
class PublicFormController extends Controller
{
    /**
     * @var PublicForm
     */
    protected $public_form;

    /**
     */
    protected $form_item;

    /**
     * @var CustomForm
     */
    protected $custom_form;
    
    /**
     * @var CustomTable
     */
    protected $custom_table;

    public function __construct(?PublicForm $public_form)
    {
        $this->public_form = $public_form;
        if(isset($public_form)){
            $this->custom_form = $public_form->custom_form;
        }
        if(isset($this->custom_form)){
            $this->form_item = $this->custom_form->form_item;
            $this->custom_table = $this->custom_form->custom_table;
            getModelName($this->custom_table);
        }
    }

    /**
     * Index interface.
     *
     * @return Content
     */
    public function index(Request $request)
    {
        return $this->getInputContent($request);
    }


    /**
     * Backed interface.
     *
     * @return Content
     */
    public function backed(Request $request)
    {
        $custom_value = $request->session()->pull(Define::SYSTEM_KEY_SESSION_PUBLIC_FORM_CONFIRM);
        return $this->getInputContent($request, $custom_value);
    }


    /**
     * Get input content (Contains form)
     *
     * @param Request $request
     * @param CustomValue|null $custom_value
     * @return void
     */
    protected function getInputContent(Request $request, ?CustomValue $custom_value = null)
    {
        $uri = boolval($this->public_form->getOption('use_confirm')) ? 'confirm' : 'create';

        $form = $this->getForm($request, $custom_value)
            ->setAction(url_join($this->public_form->getUrl(),  $uri));

        $content = new PublicContent;
        $this->public_form->setContentOption($content);

        $content->row($form);
        return $content;
    }


    /**
     * confirm interface.
     *
     * @return Content
     */
    public function confirm(Request $request)
    {
        $form = $this->getForm($request);
        $custom_value = $form->getModelByInputs();

        //TODO: validate

        $request->session()->put(Define::SYSTEM_KEY_SESSION_PUBLIC_FORM_CONFIRM, $custom_value);

        $show = $this->public_form->getShow($request, $custom_value);

        $content = new PublicContent;
        $this->public_form->setContentOption($content);

        $content->row($show);
        return $content;
    }



    /**
     * create interface.
     *
     * @return Content
     */
    public function create(Request $request)
    {
        $form = $this->getForm($request);
        $public_form = $this->public_form;
        
        //TODO: validate

        $form->saved(function($form) use($request, $public_form){
            $content = new PublicContent;
            $public_form->setContentOption($content);

            $content->row($public_form->getCompleteView($request, $form->model()));

            return response($content);
        });

        $response = $form->store();

        // Disable reload
        $request->session()->regenerateToken();

        return $response;
    }



    protected function getForm(Request $request, ?CustomValue $custom_value = null)
    { 
        return $this->public_form->getForm($request, $custom_value);
    }
}
