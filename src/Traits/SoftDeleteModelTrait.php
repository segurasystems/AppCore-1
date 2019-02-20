<?php
namespace Gone\AppCore\Traits;


trait SoftDeleteModelTrait
{
    protected $softDeleteField = "Deleted";
    protected $softDeleteDateField = "DateDeleted";
    protected $softDeleteDateFormat = "Y-m-d H:i:s";
    protected $softDelete_deleted = "yes";
    protected $softDelete_undeleted = "no";

    public function destroy(): int {
        $this->delete();
        return 1;
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
