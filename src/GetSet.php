<?php
namespace Codechap\ZohoAnalyticsApi;

trait GetSet
{
    /**
     * Get a specific property from the object
     *
     * @param string $property The property name to get
     * @return mixed The value of the property
     * @throws \InvalidArgumentException If the property does not exist
     */
    public function get(string $property)
    {
        if (!array_key_exists($property, $this->data)) {
            throw new \InvalidArgumentException(sprintf('Property "%s" does not exist', $property));
        }
        return $this->data[$property]; // Return the property value
    }

    /**
     * Set a specific property for the object
     *
     * @param string $property The property name to set
     * @param mixed $value The value to set
     * @return self Returns the current instance for method chaining
     * @throws \InvalidArgumentException If the property does not exist
     */
    public function set(string $property, $value): self
    {
        if (!array_key_exists($property, $this->data)) {
            throw new \InvalidArgumentException(sprintf('Property "%s" does not exist', $property));
        }
        if(is_array($value)){
            foreach($value AS $k => $v){
                $value[$k] = trim($v); // Trim each array value
            }
        }else{
            $value = trim($value); // Trim scalar value
        }

        $this->data[$property] = $value; // Set the property value

        return $this; // Return instance for chaining
    }
}