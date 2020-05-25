<?php

/**
 * Created by JoseChan/Admin/ControllerCreator.
 * User: admin
 * DateTime: 2020-05-24 11:11:47
 */

namespace JoseChan\Email\Admin\Controllers;

use JoseChan\Email\DataSet\Models\EmailTemplate;
use JoseChan\Base\Admin\Controllers\Controller;
use Encore\Admin\Controllers\HasResourceActions;
use Encore\Admin\Facades\Admin;
use Encore\Admin\Form;
use Encore\Admin\Grid;
use Encore\Admin\Layout\Content;

class EmailTemplateController extends Controller
{

    use HasResourceActions;

    public function index()
    {
        return Admin::content(function (Content $content) {

            //页面描述
            $content->header('邮件模版');
            //小标题
            $content->description('邮件模版管理');

            //面包屑导航，需要获取上层所有分类，根分类固定
            $content->breadcrumb(
                ['text' => '首页', 'url' => '/'],
                ['text' => '邮件模版', 'url' => '/email_templates']
            );

            $content->body($this->grid());
        });
    }

    /**
     * Edit interface.
     *
     * @param $id
     * @return Content
     */
    public function edit($id)
    {
        return Admin::content(function (Content $content) use ($id) {

            $content->header('邮件模版');
            $content->description('编辑');

            //面包屑导航，需要获取上层所有分类，根分类固定
            $content->breadcrumb(
                ['text' => '首页', 'url' => '/'],
                ['text' => '邮件模版', 'url' => '/email_templates'],
                ['text' => '编辑']
            );

            $content->body($this->form()->edit($id));
        });
    }

    /**
     * Create interface.
     *
     * @return Content
     */
    public function create()
    {
        return Admin::content(function (Content $content) {

            $content->header('邮件模版');
            $content->description('新增');

            //面包屑导航，需要获取上层所有分类，根分类固定
            $content->breadcrumb(
                ['text' => '首页', 'url' => '/'],
                ['text' => '邮件模版', 'url' => '/email_templates'],
                ['text' => '新增']
            );

            $content->body($this->form());
        });
    }

    public function grid()
    {
        return Admin::grid(EmailTemplate::class, function (Grid $grid) {

            $grid->column("id","id");
            $grid->column("name","模版名称");
            $grid->column("created_at","created_at");
            $grid->column("updated_at","updated_at");


            //允许筛选的项
            //筛选规则不允许用like，且搜索字段必须为索引字段
            //TODO: 使用模糊查询必须通过搜索引擎，此处请扩展搜索引擎
            $grid->filter(function (Grid\Filter $filter){

                $filter->where(function ($query) {
                    $query->where('name', 'like', "{$this->input}%");
                }, '模版名称');


            });


        });
    }

    protected function form()
    {
        return Admin::form(EmailTemplate::class, function (Form $form) {

            $form->display('id',"id");
            $form->file('view',"模版文件路径");
            $form->text('name',"模版名称")->rules("required|string");
            $form->datetime('created_at',"created_at");
            $form->datetime('updated_at',"updated_at");


        });
    }
}