<?php

namespace App\Http\Controllers;

use App\Dao\Models\Rs;
use App\Http\Controllers\Core\ReportController;
use App\Jobs\JobRekapKotor;
use Illuminate\Http\Request;


class ReportRekapKotorController extends ReportController
{
    public $data;

    public function __construct(Rs $model)
    {
        $this->model = $model::getModel();
    }

    public function beforeForm()
    {
        self::$share = [
            'rs' => Rs::getOptions(),
        ];
    }

    public function getData()
    {
        $query = Rs::with('has_jenis')->find(request('rs_id'));

        return $query->has_jenis()->orderBy('jenis_nama', 'ASC');
    }

    public function getPrint(Request $request)
    {
        set_time_limit(0);

        $this->data = $this->getData();

        $name = 'Report Rekap Kotor';

        $batch = exportCsv($name, $this->getData(), JobRekapKotor::class, $request->all(), env('CSV_DELIMITER', ','), 1);

        if ($request->queue == 'batch') {
            $url = moduleRoute('getCreate', array_merge(['batch' => $batch->id], $request->all()));

            return redirect()->to($url);
        }

        return moduleView(modulePathPrint(), $this->share([
            'data' => $this->data,
        ]));
    }
}
