<?php
/**
 * Created by PhpStorm.
 * User: chenyu
 * Date: 2019/1/25
 * Time: 22:22
 */

namespace JoseChan\Email\Admin\Extensions\Actions;


use Illuminate\Contracts\Support\Renderable;

class EmailQueue implements Renderable
{
    protected $resource;
    protected $key;

    public function __construct($resource, $key)
    {
        $this->resource = $resource;
        $this->key = $key;
    }

    public function render()
    {
        $uri = url("/admin/email_mission/{$this->key}/email_queues");

        return <<<EOT
<a href="{$uri}" title="发信队列">
    <i class="fa fa-bars"></i>
</a>
EOT;
    }

    public function __toString()
    {
        return $this->render();
    }
}