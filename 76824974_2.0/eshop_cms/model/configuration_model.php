<?php
class Configuration {
    public $DB_server;
    public $DB_login;
    public $DB_password;
    public $DB;
    public $NAME_MAX_LENGTH;
    public $CONTENT_MAX_LENGTH;
    public $APP_DIRECTORY;
    public $MAX_ARTICLES_COUNT_PER_PAGE;
    public $HTML_TRANSLATION_ELEMENTS_TO_CHANGE = [];
    public $ELEMENT_PARAMS = [];
    public $PRODUCT_PARAM_TITLES = [];
    public $BUTTON_CONTENT_TITLES_IN_PRODUCT_EDIT = [];
    public $PRODUCT_LIST_TITLES = [];
    public $PRODUCT_MANAGER;
    function __construct(
        $DB_server, $DB_login, $DB_password, $DB, $NAME_MAX_LENGTH, 
        $CONTENT_MAX_LENGTH, $APP_DIRECTORY, $MAX_ARTICLES_COUNT_PER_PAGE,
        $HTML_TRANSLATION_ELEMENTS_TO_CHANGE, $ELEMENT_PARAMS, $PRODUCT_PARAM_TITLES, 
        $BUTTON_CONTENT_TITLES_IN_PRODUCT_EDIT, $PRODUCT_LIST_TITLES, $PRODUCT_MANAGER) {
            
        $this->DB_server = $DB_server;
        $this->DB_login = $DB_login;
        $this->DB_password = $DB_password;
        $this->DB = $DB;
        $this->NAME_MAX_LENGTH = $NAME_MAX_LENGTH;
        $this->CONTENT_MAX_LENGTH = $CONTENT_MAX_LENGTH;
        $this->APP_DIRECTORY = $APP_DIRECTORY;
        $this->MAX_ARTICLES_COUNT_PER_PAGE = $MAX_ARTICLES_COUNT_PER_PAGE;
        $this->HTML_TRANSLATION_ELEMENTS_TO_CHANGE = [];
        $this->asignArray($this->HTML_TRANSLATION_ELEMENTS_TO_CHANGE, $HTML_TRANSLATION_ELEMENTS_TO_CHANGE);
        
        $this->ELEMENT_PARAMS = [];
        $this->asignArray($this->ELEMENT_PARAMS, $ELEMENT_PARAMS);
        
        $this->PRODUCT_PARAM_TITLES = [];
        $this->asignArray($this->PRODUCT_PARAM_TITLES, $PRODUCT_PARAM_TITLES);
        
        $this->BUTTON_CONTENT_TITLES_IN_PRODUCT_EDIT = [];
        $this->asignArray($this->BUTTON_CONTENT_TITLES_IN_PRODUCT_EDIT, $BUTTON_CONTENT_TITLES_IN_PRODUCT_EDIT);
        
        $this->PRODUCT_LIST_TITLES = [];
        $this->asignArray($this->PRODUCT_LIST_TITLES, $PRODUCT_LIST_TITLES);
        
        $this->PRODUCT_MANAGER = $PRODUCT_MANAGER;
    }
    function asignArray($array1, $array2) {
        foreach ($array2 as $element) {
            $array1[] = $element;
        }
    }
}