<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Course archive access plugin.
 *
 * This plugin adds the ability to archive a course from the course
 * settings page and stores the result in the enrolment table.
 *
 * @package    enrol
 * @subpackage coursemeta
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

class enrol_coursemeta_plugin extends enrol_plugin {
	
    /**
     * Adds enrol instance UI to course edit form
     *    
     */
    public function course_edit_form($instance, MoodleQuickForm $mform, $data, $context) {
    		
    	global $DB;
		
        // Set $i to instance id if editing, else set it to 0 for new course.
        $i = isset($instance->id) ? $instance->id : 0;
		
        // Set the header name.
        $header = $this->get_instance_name($instance);
		
        if (!$i) {
            $config = guess_if_creator_will_have_course_capability('enrol/coursemeta:config', $context);
        } else {
            $config = has_capability('enrol/coursemeta:config', $context);
        }		
		
        // Add header to the form.
        $mform->addElement('header', 'enrol_coursemeta_header_', $header);
		
        $sql = 'SELECT id, name, shortname, datatype, param1, param2, param3, param4 FROM {custom_info_field}';         
        $result = $DB->get_records_sql($sql);

        $sqlMeta = 'SELECT * FROM {custom_info_data} WHERE objectid = ? ';

        if(!empty($data->id))
        {
            $resultMeta = $DB->get_records_sql($sqlMeta,  array('objectid' => $data->id));	
        }         
        
        foreach($result as $datas)
        {	            
            if($datas->datatype == "menu")
            {  
                // explode the param1 for the menupoints
                if(!empty($datas->param1))
                {
                    $param1 = explode("\n", $datas->param1);
                }
                // create the select menu
                $select = $mform->addElement('select', $datas->shortname, get_string($datas->shortname, 'enrol_coursemeta'), $param1); 
                
                if(!empty($resultMeta))
                {
                    foreach($resultMeta as $rm)
                    {
                        if($datas->id == $rm->fieldid)
                        {
                            //set up the select menu actual value
                            $select->setSelected($rm->data);
                        }						
                    }				                    
                }                
            }
            elseif($datas->datatype == "datetime")
            {
                if(!empty($resultMeta))
                {
                    foreach($resultMeta as $rm)
                    {
                        if($datas->id == $rm->fieldid)
                        {
                            //we setup the default datetime data
                            $attributes = array(            
                                'defaulttime' => $rm->data,
                                'stopyear'  => 2040,
                                'optional'  => false
                            );
                        }
                    }
                }                    
                $selDate = $mform->addElement('date_time_selector', $datas->shortname, get_string($datas->shortname, 'enrol_coursemeta'), $attributes);                
            }
            else 
            {
                $mform->addElement($datas->datatype, $datas->shortname,
                        get_string($datas->shortname, 'enrol_coursemeta'));

                // we add the metafield fields value to the frontend
                if(!empty($resultMeta))
                {
                    foreach($resultMeta as $rm)
                    {
                        if($datas->id == $rm->fieldid)
                        {	/* add field value - name can contains space, this is why we use shortname */
                                $data->{$datas->shortname} = $rm->data;						 
                        }						
                    }				
                }
            }
        }
        
    }

    /**
     * Validates course edit form data
     *    
     */
    public function course_edit_validation($instance, array $data, $context) {
        $errors = array();

        return $errors;
    }
	
    public function get_meta_fields_from_db()
    {
        global $DB;

        $sql = '
            SELECT 
                id, name, shortname, datatype, param1, param2, param3, param4
            FROM 
                {custom_info_field}
            WHERE 
                objectname = "course"
                                ';    
        $instances = $DB->get_records_sql($sql);

        return $instances;	

    }

    public function get_course_meta_fields_from_db($courseid)
    {
        global $DB;

        if(empty($courseid))
        {
                return false;
        }

        $sql = 'SELECT 
                    cif.id as cifid, cid.objectid as courseid, cif.name as keyname, 
                    cid.data as keyvalue, cid.id as itemid, cif.shortname as shortname
                FROM {custom_info_data} as cid
                LEFT JOIN {custom_info_field} as cif on cid.fieldid = cif.id
                WHERE cid.objectid = ?';    					     

        $instances = $DB->get_records_sql($sql, array('objectid' => $courseid));

        return $instances;
    }
	
    public function add_metafield_to_DB($fields, $courseid, $data)
    {
        global $DB;
        
        foreach ($fields as $field) 
        {			
            $keyname = $field->shortname;
            $keyvalue = $data->$keyname;
            $keytype = $field->datatype;
            // keyname and value is required and if the type is menu 
            // then we need to save the default value which is 0
            if (isset($keyname) && !empty($keyvalue) || (isset($keyname) && $keytype == "menu") ) 
            {						
                $record = new stdClass();
                $record->objectid = $courseid;
                $record->fieldid = $field->id;
                $record->data = $keyvalue;
                $record->objectname = "course";                                     

                $records = array($record);					

                $sql = $DB->insert_records('custom_info_data', $records, false);	
            }	
        }
    }


    /**
     * Called after updating/inserting course.
     *
     * @param bool $inserted true if course just inserted
     * @param object $course
     * @param object $data form data
     * @return void
     */
     
    public function course_updated($inserted, $course, $data) 
    {
        global $DB;
		
        // if we add a new course 
        if($inserted) 
        {
            // we add the coursemeta enrol to the course - mdl_enrol db table 	
            $this->add_instance($course);
			            	
            // get the meta fields from db
            $instances = $this->get_meta_fields_from_db();

            foreach ($instances as $instance) 
            {
                $fieldid = $instance->id;				                					
                $shortname = $instance->shortname;

                if (isset($data->$shortname)) 
                {					
                    $record = new stdClass();
                    $record->objectname = "course";
                    $record->objectid = $course->id;
                    $record->fieldid = $fieldid;
                    $record->data = $data->$shortname;

                    $DB->insert_record('custom_info_data', $record, false);
                }								
            }
			
        } else { 		
			
            // get the meta fields from db
            $fields = $this->get_meta_fields_from_db();
			
            // get the fields from the DB 
            $instances = $this->get_course_meta_fields_from_db($course->id);
            
            // the course has meta fields
            if (!empty($instances)) 
            {
                // 1. delete the existing keys and values	
                foreach($instances as $instance)
                {										
                    $record = new stdClass();
                    $courseid = $instance->courseid;
                    $DB->delete_records('custom_info_data', array('objectid'=>$courseid));	
                }
                // add the new keys and values
                $courseid = $course->id;				
                $this->add_metafield_to_DB($fields, $courseid, $data);				

            }

            // the course has no meta fields yet
            if (empty($instances)) 
            {				
                $courseid = $course->id;				
                $this->add_metafield_to_DB($fields, $courseid, $data);				
            }
				
        }        
    }

    public function can_add_instance($courseid) {
        return true;
    }
		 
}
