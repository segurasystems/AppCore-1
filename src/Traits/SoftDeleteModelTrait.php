<?php
namespace Gone\AppCore\Traits;


trait SoftDeleteModelTrait
{
    protected $softDeleteField = "Deleted";
    protected $softDeleteDateField = null;
    protected $softDeleteDateFormat = "Y-m-d H:i:s";
    protected $softDelete_deleted = true;
    protected $softDelete_undeleted = false;

    public function destroy(){
        $this->delete();
    }

    public function delete()
    {
        $method = "set{$this->softDeleteField}";
        $this->$method($this->softDelete_deleted);
        if(!empty($this->softDeleteDate)){
            $method = "set{$this->softDeleteDateField}";
            $this->$method(date($this->softDeleteDateFormat));
        }
        return $this->save();
    }

    public function reinstate(){
        $method = "set{$this->softDeleteField}";
        $this->$method($this->softDelete_undeleted);
        if(!empty($this->softDeleteDate)){
            $method = "set{$this->softDeleteDateField}";
            $this->$method(null);
        }
        return $this->save();
    }
}
