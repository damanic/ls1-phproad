<?php
namespace Backend;

use Db\File;

class Files extends Controller
{
    public function get($id)
    {
        $this->suppressView();
        try {
            $file = File::create()->find($id);
            if ($file) {
                $file->output();
            }
        } catch (\Exception $ex) {
            echo $ex->getMessage();
        }
    }
}
