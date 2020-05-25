<?php

/**
 * Created by JoseChan/Admin/ControllerCreator.
 * User: admin
 * DateTime: 2020-05-24 11:03:36
 */

namespace JoseChan\Email\Admin\Controllers;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\MessageBag;
use JoseChan\Email\DataSet\Models\EmailMission;
use JoseChan\Base\Admin\Controllers\Controller;
use Encore\Admin\Controllers\HasResourceActions;
use Encore\Admin\Facades\Admin;
use Encore\Admin\Form;
use Encore\Admin\Grid;
use Encore\Admin\Layout\Content;
use JoseChan\Email\DataSet\Models\EmailQueue;
use JoseChan\Email\DataSet\Models\EmailTemplate;
use JoseChan\SendCloud\Sdk\SendCloud;
use Maatwebsite\Excel\Facades\Excel;
use Maatwebsite\Excel\Readers\LaravelExcelReader;

class EmailMissionController extends Controller
{

    use HasResourceActions;

    public function index()
    {
        return Admin::content(function (Content $content) {

            //页面描述
            $content->header('发信任务');
            //小标题
            $content->description('发信任务管理');

            //面包屑导航，需要获取上层所有分类，根分类固定
            $content->breadcrumb(
                ['text' => '首页', 'url' => '/'],
                ['text' => '发信任务', 'url' => '/email_missions']
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

            $content->header('发信任务');
            $content->description('编辑');

            //面包屑导航，需要获取上层所有分类，根分类固定
            $content->breadcrumb(
                ['text' => '首页', 'url' => '/'],
                ['text' => '发信任务', 'url' => '/email_missions'],
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

            $content->header('发信任务');
            $content->description('新增');

            //面包屑导航，需要获取上层所有分类，根分类固定
            $content->breadcrumb(
                ['text' => '首页', 'url' => '/'],
                ['text' => '发信任务', 'url' => '/email_missions'],
                ['text' => '新增']
            );

            $content->body($this->form());
        });
    }

    public function grid()
    {
        /** @var SendCloud $send_cloud_sdk */
        $send_cloud_sdk = app()->make(SendCloud::class);
        $user_info = $send_cloud_sdk->get(env("SEND_CLOUD_USER"), env("SEND_CLOUD_KEY"));
        $result = json_decode($user_info->getBody()->getContents(), true);

        return Admin::grid(EmailMission::class, function (Grid $grid) use ($result) {

            $grid->column("id", "id");
            $grid->column("template_id", "模版ID");
            $grid->column("subject", "主题");
            $grid->column("from_email", "发件人账号");
            $grid->column("from_name", "发件人");
            $grid->column("send_at", "发送时间");
            $grid->header(function ($query) use ($result) {
                echo "<div style='padding: 10px'>
可用余额：<span style='color: darkseagreen;margin-right: 10px;'>{$result['info']['avaliableBalance']}</span>
今日请求额度：<span style='color: red'>{$result['info']['todayUsedQuota']}</span>/<span style='color: darkseagreen'>{$result['info']['quota']}</span>
</div>";
            });
            $grid->actions(function (Grid\Displayers\Actions $actions) {
                $actions->disableEdit();
                $actions->append(new \JoseChan\Email\Admin\Extensions\Actions\EmailQueue(
                    $actions->getResource(),
                    $actions->getKey()
                ));
            });

            //允许筛选的项
            //筛选规则不允许用like，且搜索字段必须为索引字段
            //TODO: 使用模糊查询必须通过搜索引擎，此处请扩展搜索引擎
            $grid->filter(function (Grid\Filter $filter) {

                $filter->equal("template_id", "模版ID");
                $filter->between("send_at", "发送时间")->datetime();


            });


        });
    }

    protected function form()
    {
        /** @var SendCloud $send_cloud_sdk */
        $send_cloud_sdk = app()->make(SendCloud::class);
        $user_info = $send_cloud_sdk->get(env("SEND_CLOUD_USER"), env("SEND_CLOUD_KEY"));
        $result = json_decode($user_info->getBody()->getContents(), true);

        return Admin::form(EmailMission::class, function (Form $form) use ($result) {

            $form->display('id', "id");
            $form->select('template_id', "模版")->options(EmailTemplate::getSelector());
            $form->text('subject', "主题")->rules("required|string");
            $form->email('from_email', "发件人账号");
            $form->text('from_name', "发件人")->rules("required|string");
            $form->file('to_email', "导入收件人");
            $form->datetime('send_at', "发送时间")->format('YYYY-MM-DD HH:mm:ss');
            $form->datetime('created_at', "created_at");
            $form->datetime('updated_at', "updated_at");
            $form->hidden("queues");

            $form->ignore(['to_email']);
            $form->saving(function (Form $form) use ($result) {

                //读取sendcloud额度，以今天为准
                $quota = isset($result['info']['quota']) ? $result['info']['quota'] : 0;

                //读取发件日期已创建的发件队列数量
                $date = Carbon::parse($form->input("send_at"));
                $start = $date->format("Y-m-d");
                $end = $date->addDay(1)->format("Y-m-d");
                $count = EmailQueue::countQueueByMission([
                    ["email_missions.send_at", ">=", $start],
                    ["email_missions.send_at", "<", $end],
                ]);

                //读取excel数据
                $file = request()->file("to_email")->path();
                $reader = Excel::load($file);

                /** @var Collection $collection */
                $collection = $reader->get();

                $email_list = array_column($collection->toArray(), 0);

                //新导入的数据行数加上已有的行数大于发送额度
                if (($count + count($email_list)) > $quota) {
                    throw new \Exception('创建的邮件消息队列数超过当天额度上限');
                }

                $models = EmailQueue::buildManyByEmailList($email_list, false);

                foreach ($models as &$model) {
                    $model[Form::REMOVE_FLAG_NAME] = 0;
                }

                $form->input("queues", $models);
            });
        });
    }
}