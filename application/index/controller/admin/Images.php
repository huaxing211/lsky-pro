<?php
/**
 * User: Wisp X
 * Date: 2018/9/27
 * Time: 上午10:29
 * Link: https://github.com/wisp-x
 */

namespace app\index\controller\admin;

use app\common\model\Images as ImagesModel;
use app\common\model\Users;
use think\facade\Config;
use think\Db;
use think\Exception;

/**
 * 图片管理
 *
 * Class Images
 * @package app\index\controller\admin
 */
class Images extends Base
{
    private $strategyList = [];

    public function initialize()
    {
        parent::initialize();
        $this->strategyList = Config::pull('strategy');
        $this->assign('strategyList', $this->strategyList);
    }

    public function index($strategy = '', $keyword = '', $limit = 15)
    {
        $model = new ImagesModel();
        if (!empty($strategy)) {
            $model = $model->where('strategy', $strategy);
        }
        if (!empty($keyword)) {
            $model = $model->where('pathname|sha1|md5', 'like', "%{$keyword}%");
        }
        $images = $model->order('id', 'desc')->paginate($limit, false, [
            'query' => [
                'keyword' => $keyword
            ]
        ])->each(function ($item) {
            $item->username = Users::where('id', $item->user_id)->value('username');
            $item->strategyStr = $this->strategyList[$item->strategy]['name'];
            return $item;
        });
        $this->assign([
            'images' => $images,
            'keyword' => $keyword,
            'strategyList' => $this->strategyList,
            'strategy' => $strategy
        ]);
        return $this->fetch();
    }

    public function delete()
    {
        if ($this->request->isPost()) {
            Db::startTrans();
            try {
                $id = $this->request->post('id');
                $deletes = []; // 需要删除的文件
                if (is_array($id)) {
                    $images = ImagesModel::all($id);
                    foreach ($images as &$value) {
                        $deletes[$value->strategy][] = $value->pathname;
                        $value->delete();
                        unset($value);
                    }
                } else {
                    $image = ImagesModel::get($id);
                    if (!$image) {
                        throw new Exception('没有找到该图片数据');
                    }
                    $deletes[$image->strategy][] = $image->pathname;
                    $image->delete();
                }
                // 是否开启软删除(开启了只删除记录，不删除文件)
                if (!$this->config['soft_delete']) {
                    $strategy = [];
                    // 实例化所有储存策略驱动
                    $strategyAll = array_keys($this->strategyList);
                    foreach ($strategyAll as $value) {
                        // 获取储存策略驱动
                        $strategy[$value] = $this->getStrategyInstance($value);
                    }

                    foreach ($deletes as $key => $val) {
                        $strategy[$key]->deletes($val);
                    }
                }
                Db::commit();
            } catch (Exception $e) {
                Db::rollback();
                return $this->error($e->getMessage());
            }
            return $this->success('删除成功');
        }
    }
}