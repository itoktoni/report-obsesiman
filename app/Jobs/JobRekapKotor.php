<?php

namespace App\Jobs;

use App\Dao\Models\Rs;
use Carbon\Carbon;
use Illuminate\Bus\Batchable;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Laravie\SerializesQuery\Eloquent;
use Spatie\SimpleExcel\SimpleExcelWriter;

class JobRekapKotor implements ShouldQueue
{
    use Batchable;
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public $tries = 5;

    private $rs_id;
    private $start_date;
    private $end_date;

    public function __construct(
        public $fileName,
        public $query,
        public $request,
        public $chunkIndex,
        public $chunkSize,
        public $delimiter
    ) {
        $this->rs_id = $request['rs_id'];
        $this->start_date = $request['start_date'];
        $this->end_date = $request['end_date'];
    }

    private function getHeader()
    {
        $header = [
            'name' => 'Nama Linen',
        ];

        $rs = Rs::with(['has_ruangan'])->find($this->rs_id);
        $ruangan = $rs->has_ruangan;
        $ruangan_pluck = $ruangan->pluck('ruangan_nama')->toArray();

        $data = array_merge($header, $ruangan_pluck, [
            'Belum Register',
            'Beda Rs',
            'Total Kotor',
            '(Kg) Kotor',
            'Total Bersih (pcs)',
            '-',
            '+',
        ]);

        return $data;
    }

    private function getQueryKotor()
    {
        $query = DB::connection('report')->table('view_rekap_kotor')
            ->where('view_rs_id', $this->rs_id);

        $query = $query->where('view_tanggal', '>=', $this->start_date);
        $query = $query->where('view_tanggal', '<=', $this->end_date);

        return $query->get();
    }

    private function getQueryBersih()
    {
        $query = DB::connection('report')
            ->table('view_rekap_bersih')
            ->where('view_rs_id', $this->rs_id);

        $bersih_from = Carbon::createFromFormat('Y-m-d', $this->start_date) ?? false;
        if ($bersih_from) {
            $query = $query->where('view_tanggal', '>=', $bersih_from->addDay(1)->format('Y-m-d'));
        }

        $bersih_to = Carbon::createFromFormat('Y-m-d', $this->end_date) ?? false;
        if ($bersih_to) {
            $query = $query->where('view_tanggal', '<=', $bersih_to->addDay(1)->format('Y-m-d'));
        }

        return $query->get();
    }

    public function handle()
    {
        $query = Eloquent::unserialize($this->query)
            ->select('*');

        if ($this->chunkIndex == 1)
        {
            $excel = SimpleExcelWriter::create($this->fileName, delimiter: $this->delimiter);
            $excel->addHeader(array_values($this->getHeader()));

        } else {

            if (! file_exists($this->fileName))
            {
                SimpleExcelWriter::create($this->fileName, delimiter: $this->delimiter)
                    ->addHeader(array_values($this->getHeader()));
            }

            $data_query = $query
            ->orderBy('jenis_nama', 'asc')
            ->skip(($this->chunkIndex - 1) * $this->chunkSize)
            ->take($this->chunkSize)
            ->get();

            if (Cache::has($this->fileName))
            {
                $rekap_kotor = Cache::get($this->fileName)['rekap_kotor'];
                $rekap_bersih = Cache::get($this->fileName)['rekap_bersih'];
            }
            else
            {
                // $query_kotor_per_ruangan = "
                // SELECT *
                // FROM view_rekap_kotor
                // WHERE view_rs_id = $rs_id
                //     AND view_tanggal >= '$start'
                //     AND view_tanggal <= '$end'
                // ";

                // $rekap_kotor = DB::connection('report')->select($query_kotor_per_ruangan);

                // $query_bersih_per_ruangan = "
                // SELECT *
                // FROM view_rekap_bersih
                // WHERE view_rs_id = $rs_id
                //     AND view_tanggal >= '$start'
                //     AND view_tanggal <= '$end'
                // ";

                // $rekap_bersih = DB::connection('report')->select($query_bersih_per_ruangan);

                $rekap_kotor = $this->getQueryKotor();
                $rekap_bersih = $this->getQueryBersih();

                Cache::add($this->fileName, [
                    'rekap_kotor' => $rekap_kotor,
                    'rekap_bersih' => $rekap_bersih
                ], now()->addMinutes(5));
            }

            $rs = Rs::with(['has_ruangan'])->find($this->rs_id);
            $ruangan = $rs->has_ruangan;

            foreach($data_query as $jenis)
            {
                $data[$this->chunkIndex] = $this->chunkIndex;
                $data[$jenis->jenis_id] = $jenis->jenis_nama;

                foreach($ruangan as $room)
                {
                    // $data[$room->ruangan_id] = $room->ruangan_nama;

                    $total = collect($rekap_kotor)
                        ->where('view_linen_id', $jenis->jenis_id)
                        ->where('view_ruangan_id', $room->ruangan_id)
                        ->sum('view_qty');

                    $data[$room->ruangan_id] = $total ?? 0;
                }

                $data['belum_register'] = '';
                $data['beda_rs'] = '';

                $total_kotor = collect($rekap_kotor)
                ->where('view_linen_id', $jenis->jenis_id)
                ->sum('view_qty');

                $data['total_kotor'] = $total_kotor;

                $total_kg = collect($rekap_kotor)
                ->where('view_linen_id', $jenis->jenis_id)
                ->sum('view_kg');

                $data['total_kg'] = $total_kg;

                $total_bersih = collect($rekap_bersih)
                ->where('view_linen_id', $jenis->jenis_id)
                ->sum('view_qty');

                $data['total_bersih'] = $total_bersih;

                $selisih = $total_bersih - $total_kotor;

                $data['minus'] = $selisih < 0 ? $selisih : 0;
                $data['plus'] = $selisih > 0 ? $selisih : 0;
            }

            $file = $this->fileName;
            $open = fopen($file, 'a+');

            fputcsv($open, $data, env('CSV_DELIMITER', ','));

            fclose($open);
        }

    }

    private function usersGenerator($users)
    {
        foreach ($users as $user) {
            yield $user;
        }
    }

    public function middleware()
    {
        return [new WithoutOverlapping('export', 10)];
    }
}
