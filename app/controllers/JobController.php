<?php

namespace app\controllers;
use Yii;
use yii\console\Controller;
use yii\console\Exception;
use yii\helpers\FileHelper;

class JobController extends Controller
{
    /**
     * @var string output csv filename default to ./data.csv
     */
    public $output = 'data.csv';

    /**
     * @var string email address target
     */
    public $email;

    public function options($actionID)
    {
        return ['output', 'email', 'help'];
    }

    public function optionAliases()
    {
        return ['o' => 'output', 'e' => 'email', 'h' => 'help'];
    }

    /**
     * Returns the full file name.
     * @return string full file name.
     */
    public function getFullFileName()
    {
        if (!strlen($this->output)) {
            throw new Exception('PLease specify to output filename with -o see the help (-h)');
        }
        $output = $this->output;
        if ($this->output[0] == '@') {
            $output = Yii::getAlias($this->output);
        }
        if (preg_match('@^(https?:)?//@i', $output)) {
            throw new Exception('Unsupported target output');
        }
        if ($output[0]!='/' && $output[0]!='.') {
            $output = __DIR__ . '/../../' . $output;
        }
        return $output;
    }

    /**
     * Resolves given file path, making sure it exists and writeable.
     * @param string $path file path.
     * @return bool success.
     *@throws \yii\base\Exception on failure.
     */
    protected function resolvePath($path)
    {
        FileHelper::createDirectory($path, 0777);
        if (!is_dir($path)) {
            throw new Exception("Unable to resolve path: '{$path}'!");
        } elseif (!is_writable($path)) {
            throw new Exception("Path: '{$path}' should be writable!");
        }
        return true;
    }

    /**
     * Default action
     *
     */
    public function actionIndex()
    {
        $stream = Yii::$app->s3->readStream('challenge-1-in.jsonl');
        $contents = '';

        $this->resolvePath(dirname($this->getFullFileName()));
        $csv = fopen($this->getFullFileName(), 'w');

        $i = 0;
        while (!feof($stream)) {
            $contents .= stream_get_contents($stream, 2000);
            if (FALSE===(strpos($contents, "\n"))) {
                continue;
            }

            while (FALSE!==($pos = strpos($contents, "\n"))) {
                $json = substr($contents, 0, $pos + 1);
                $contents = substr($contents, $pos + 1);
                $data = json_decode($json, true);

                if ($data && !$i) {
                    fputcsv($csv, [
                        'order_id',
                        'order_datetime',
                        'total_order_value',
                        'average_unit_price',
                        'distinct_unit_count',
                        'total_units_count',
                        'customer_state',
                        'Lat',
                        'Long'
                    ]);
                }

                if ($data)
                {
                    $total_order_value = $this->calculateTotalOrderValue($data);
                    if (!$total_order_value) {
                        continue;
                    }

                    $latlong = $this->getLatLong($data);
                    fputcsv($csv, [
                        $data['order_id'],
                        $data['order_date'],
                        $total_order_value,
                        $this->averagePriceUnit($data),
                        count($data['items']),
                        $this->getUnitCount($data),
                        $data['customer']['shipping_address']['state'],
                        $latlong['lat'],
                        $latlong['lng'],
                    ]);
                    $i++;
                }

            }
        }
        fclose($csv);
        fclose($stream);

        if ($this->email) {
            return Yii::$app->mailer->compose()->attach($this->getFullFileName())->setTo($this->email)
                ->setFrom(['no-reply@catch.com' => 'System'])
                ->setSubject('Catch Cron Job')
                ->setTextBody('Catch Cron Job')
                ->send();
        }
    }

    protected function getLatLong ($data) {
        $address = $data['customer']['shipping_address']['street'] . ', ' .
            $data['customer']['shipping_address']['suburb'] . ', ' .
            $data['customer']['shipping_address']['state'] . ' ' . $data['customer']['shipping_address']['postcode'];
        $json = file_get_contents('https://www.mapquestapi.com/geocoding/v1/address?key=Dtm3Cp3G3K2Hahgw8TI0acdEKVGPBda3&inFormat=kvp&outFormat=json&location=' . urlencode($address) . '&thumbMaps=false');
        $result = json_decode($json, true);

        if (!isset($result['results'][0]['locations'][0]['latLng'])) {
            return ['lat' => '', 'lng' => ''];
        }
        return $result['results'][0]['locations'][0]['latLng'];
    }

    protected function calculateTotalOrderValue($data) {
        $total = 0;

        foreach($data['items'] as $item) {
            $total += $item['quantity'] * $item['unit_price'];
        }
        foreach($data['discounts'] as $discount) {
            if ($discount['type'] == 'DOLLAR') {
                $total -= $discount['value'];
            } elseif ($discount['type'] == 'PERCENTAGE') {
                $total -= ($discount['value'] * $total);
            }
        }
        return $total;
    }

    protected function averagePriceUnit($data) {
        $total = 0;
        $numberofItems = 0;
        foreach($data['items'] as $item) {
            $total += $item['quantity'] * $item['unit_price'];
            $numberofItems += $item['quantity'];
        }
        if ($numberofItems) {
            return $total / $numberofItems;
        }
        return $total;
    }

    protected function getUnitCount($data) {
        $numberofItems = 0;
        foreach($data['items'] as $item) {
            $numberofItems += $item['quantity'];
        }

        return $numberofItems;
    }
}