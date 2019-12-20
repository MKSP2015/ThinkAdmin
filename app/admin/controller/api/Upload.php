<?php

// +----------------------------------------------------------------------
// | ThinkAdmin
// +----------------------------------------------------------------------
// | 版权所有 2014~2019 广州楚才信息科技有限公司 [ http://www.cuci.cc ]
// +----------------------------------------------------------------------
// | 官方网站: http://demo.thinkadmin.top
// +----------------------------------------------------------------------
// | 开源协议 ( https://mit-license.org )
// +----------------------------------------------------------------------
// | gitee 代码仓库：https://gitee.com/zoujingli/ThinkAdmin
// | github 代码仓库：https://github.com/zoujingli/ThinkAdmin
// +----------------------------------------------------------------------

namespace app\admin\controller\api;

use think\admin\Controller;
use think\admin\Storage;
use think\admin\storage\AliossStorage;
use think\admin\storage\LocalStorage;
use think\admin\storage\QiniuStorage;

/**
 * 文件上传接口
 * Class Upload
 * @package app\admin\controller\api
 */
class Upload extends Controller
{

    /**
     * 上传安全检查
     * @login true
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\DbException
     * @throws \think\db\exception\ModelNotFoundException
     */
    public function check()
    {
        $exts = array_intersect(explode(',', input('exts', '')), explode(',', sysconf('storage.allow_exts')));
        $this->success('获取文件上传参数', ['exts' => join('|', $exts), 'mime' => Storage::mime($exts)]);
    }

    /**
     * 检查文件上传已经上传
     * @login true
     * @throws \think\Exception
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\DbException
     * @throws \think\db\exception\ModelNotFoundException
     */
    public function state()
    {
        $data = ['uptype' => $this->getType(), 'xkey' => input('xkey')];
        if ($info = Storage::instance($data['uptype'])->info($data['xkey'])) {
            $data['url'] = $info['url'];
            $data['pathinfo'] = $info['file'];
            $this->success('文件已经上传', $data, 200);
        } elseif ('local' === $data['uptype']) {
            $data['url'] = LocalStorage::instance()->url($data['xkey']);
            $data['server'] = LocalStorage::instance()->upload();
        } elseif ('qiniu' === $data['uptype']) {
            $data['url'] = QiniuStorage::instance()->url($data['xkey']);
            $data['token'] = QiniuStorage::instance()->buildUploadToken($data['xkey']);
            $data['server'] = QiniuStorage::instance()->upload();
        } elseif ('alioss' === $data['uptype']) {
            $token = AliossStorage::instance()->buildUploadToken($data['xkey']);
            $data['server'] = AliossStorage::instance()->upload();
            $data['url'] = $token['siteurl'];
            $data['policy'] = $token['policy'];
            $data['signature'] = $token['signature'];
            $data['OSSAccessKeyId'] = $token['keyid'];
        }
        $this->success('获取上传参数', $data, 404);
    }

    /**
     * 文件上传入口
     * @login true
     * @return \think\response\Json
     * @throws \think\Exception
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\DbException
     * @throws \think\db\exception\ModelNotFoundException
     */
    public function file()
    {
        if (!($file = $this->getFile()) || empty($file)) {
            return json(['uploaded' => false, 'error' => ['message' => '文件上传异常，文件可能过大或未上传']]);
        }
        $this->extension = $file->getOriginalExtension();
        if (!in_array($this->extension, explode(',', sysconf('storage.allow_exts')))) {
            return json(['uploaded' => false, 'error' => ['message' => '文件上传类型受限，请在后台配置']]);
        }
        if (in_array($this->extension, ['php', 'sh'])) {
            return json(['uploaded' => false, 'error' => ['message' => '可执行文件禁止上传到本地服务器']]);
        }
        list($this->safe, $this->uptype) = [boolval(input('safe')), $this->getType()];
        $name = Storage::name($file->getPathname(), $this->extension, '', 'md5_file');
        $info = Storage::instance($this->uptype)->set($name, file_get_contents($file->getRealPath()), $this->safe);
        if (is_array($info) && isset($info['url'])) {
            return json(['uploaded' => true, 'filename' => $name, 'url' => $this->safe ? $name : $info['url']]);
        } else {
            return json(['uploaded' => false, 'error' => ['message' => '文件处理失败，请稍候再试！']]);
        }
    }

    /**
     * 获取文件上传方式
     * @return string
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\DbException
     * @throws \think\db\exception\ModelNotFoundException
     */
    private function getType()
    {
        $this->uptype = input('uptype');
        if (!in_array($this->uptype, ['local', 'qiniu', 'alioss'])) {
            $this->uptype = sysconf('storage.type');
        }
        return $this->uptype;
    }

    /**
     * 获取本地文件对象
     * @return \think\file\UploadedFile
     */
    private function getFile()
    {
        try {
            return $this->request->file('file');
        } catch (\Exception $e) {
            $this->error(lang($e->getMessage()));
        }
    }

}