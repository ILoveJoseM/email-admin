<?php

/**
 * Created by JoseChan/Admin/ControllerCreator.
 * User: admin
 * DateTime: 2020-05-25 08:59:16
 */

namespace JoseChan\Email\Admin\Controllers;

use Carbon\Carbon;
use function foo\func;
use JoseChan\Email\DataSet\Models\EmailMission;
use JoseChan\Email\DataSet\Models\EmailQueue;
use App\Http\Controllers\Controller;
use Encore\Admin\Controllers\HasResourceActions;
use Encore\Admin\Facades\Admin;
use Encore\Admin\Form;
use Encore\Admin\Grid;
use Encore\Admin\Layout\Content;
use JoseChan\SendCloud\Sdk\SendCloud;

class EmailQueueController extends Controller
{

    use HasResourceActions;

    public function index($email_mission)
    {
        return Admin::content(function (Content $content) use ($email_mission) {

            //页面描述
            $content->header('发信队列');
            //小标题
            $content->description('发信队列管理');

            //面包屑导航，需要获取上层所有分类，根分类固定
            $content->breadcrumb(
                ['text' => '首页', 'url' => '/'],
                ['text' => '邮件任务', 'url' => '/email_missions'],
                ['text' => '发信队列']
            );

            $content->body($this->grid($email_mission));
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

            $content->header('发信队列');
            $content->description('编辑');

            //面包屑导航，需要获取上层所有分类，根分类固定
            $content->breadcrumb(
                ['text' => '首页', 'url' => '/'],
                ['text' => '发信队列', 'url' => ''],
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
    public function create($email_mission)
    {
        return Admin::content(function (Content $content) use ($email_mission) {

            $content->header('发信队列');
            $content->description('新增');

            //面包屑导航，需要获取上层所有分类，根分类固定
            $content->breadcrumb(
                ['text' => '首页', 'url' => '/'],
                ['text' => '邮件任务', 'url' => '/email_missions'],
                ['text' => '发信队列'],
                ['text' => '新增']
            );

            $content->body($this->form($email_mission));
        });
    }

    public function grid($email_mission)
    {
        return Admin::grid(EmailQueue::class, function (Grid $grid) use ($email_mission) {

            $grid->model()->where("mission_id", "=", $email_mission);
            $grid->column("id", "id");
            $grid->column("to_email", "发件人账号");
            $grid->column("err_msg", "备注");
            $grid->column("status", "状态")->using([0 => "待发送", 1 => "已发送", 2 => "发送失败"]);

            $grid->actions(function (Grid\Displayers\Actions $actions) {
                $actions->disableEdit();
            });
            //允许筛选的项
            //筛选规则不允许用like，且搜索字段必须为索引字段
            //TODO: 使用模糊查询必须通过搜索引擎，此处请扩展搜索引擎
            $grid->filter(function (Grid\Filter $filter) {

                $filter->where(function ($query) {
                    $query->where('to_email', 'like', "{$this->input}%");
                }, '发件人账号');
                $filter->equal("status", "状态")->select([0 => "待发送", 1 => "已发送", 2 => "发送失败"]);


            });

        });
    }

    protected function form($email_mission)
    {

        /** @var SendCloud $send_cloud_sdk */
        $send_cloud_sdk = app()->make(SendCloud::class);
        $user_info = $send_cloud_sdk->get(env("SEND_CLOUD_USER"), env("SEND_CLOUD_KEY"));
        $result = json_decode($user_info->getBody()->getContents(), true);

        return Admin::form(EmailQueue::class, function (Form $form) use ($email_mission, $result) {

            $form->display('id', "id");
            $form->email('to_email', "发件人账号");
            $form->hidden('mission_id')->default($email_mission);
            $form->hidden('status')->default(0);
            $form->hidden('err_msg')->default("");
            $form->datetime('created_at', "created_at");
            $form->datetime('updated_at', "updated_at");

            $form->saving(function (Form $form) use ($email_mission, $result) {

                //读取sendcloud额度，以今天为准
                $quota = isset($result['info']['quota']) ? $result['info']['quota'] : 0;

                //读取发件日期已创建的发件队列数量
                /** @var EmailMission $mission */
                $mission = EmailMission::query()->find($email_mission);
                $date = Carbon::parse($mission->send_at);
                $start = $date->format("Y-m-d");
                $end = $date->addDay(1)->format("Y-m-d");
                $count = EmailQueue::countQueueByMission([
                    ["email_missions.send_at", ">=", $start],
                    ["email_missions.send_at", "<", $end],
                ]);

                if ($count >= $quota) {
                    throw new \Exception('创建的邮件消息队列数超过当天额度上限');
                }
            });

        });
    }

    public function update($id)
    {
        return $this->form()->update($id);
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param $email_mission
     * @return mixed
     */
    public function store($email_mission)
    {
        return $this->form($email_mission)->store();
    }
}