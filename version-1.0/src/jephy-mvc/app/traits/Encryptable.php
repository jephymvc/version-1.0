<?php
namespace App\Traits;
// jephy-mvc/app/traits/Encryptable.php


trait Encryptable
{
    protected $encryption;
    protected $encryptable = [];
    
    protected function getEncryption()
    {
        if (!$this->encryption) {
            $this->encryption = new App\Core\Encryption();
        }
        return $this->encryption;
    }
    
    public function setAttribute($key, $value)
    {
        if (in_array($key, $this->encryptable)) {
            $value = $this->getEncryption()->encrypt($value);
        }
        
        return parent::setAttribute($key, $value);
    }
    
    public function getAttribute($key)
    {
        $value = parent::getAttribute($key);
        
        if (in_array($key, $this->encryptable) && $value) {
            $value = $this->getEncryption()->decrypt($value);
        }
        
        return $value;
    }
}

// Usage
#	class MedicalRecord extends Model
#	{
#	    use Encryptable;
#	    
#	    protected $encryptable = ['diagnosis', 'prescription', 'patient_notes'];
#	}