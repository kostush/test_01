<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Ramsey\Uuid\Type\Time;

class Currency extends Model
{
    use HasFactory;

    /**
     * @var string[]
     * @property id
     * @property base
     * @property historical
     * @property latest
     */

    //public $base ;//= "USD";
  /*  protected $fillable=[
        'base','latest','historical'
    ];*/
    protected $table = "currency";

    //***** Mutators**************
    public function setLatestAttribute ($value)
    {
        $this->attributes['latest'] = json_encode($value);
    }

    public function getLatestAttribute($value)
    {
        return json_decode($value,true);
    }
    public function setHistoricalAttribute ($value)
    {
        $this->attributes['historical'] = json_encode($value);
    }

    public function getHistoricalAttribute($value)
    {
        return json_decode($value,true);
    }
    //****************************

    public function setCurrencyRate($base,$rate_array)
    {

        $this->historical = $this->getCurrencyRate($base);
        $this->latest = $rate_array;
        $this->base = $base;
        $this->save();
    }

    public function getCurrencyRate($base):array
    {
        $latest=[];
        $Currency =  $this->where('base',$base)->first();
        if ($Currency) $latest = $Currency->latest;
        return  $latest;

    }

    public function updateCurrencyRate()
    {
        $Currencies = $this::all();
    }
}
