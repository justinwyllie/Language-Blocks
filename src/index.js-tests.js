import { registerBlockType } from '@wordpress/blocks';
import { useBlockProps } from '@wordpress/block-editor';
import { useState, useEffect } from '@wordpress/element'; // this is the abstraction for react?
import { TextControl } from '@wordpress/components';
import { useSelect } from '@wordpress/data';
import { useEntityProp } from '@wordpress/core-data';
import Form from 'react-bootstrap/Form';
import Row from 'react-bootstrap/Row';
import Col from 'react-bootstrap/Col';
import { Button} from 'react-bootstrap';
import { Formik, Field, ErrorMessage } from 'formik'; 
//import bootstrap CSS 
//rather lucily the webpack installed by wp-scripts includes a process for scss
//and by default builds it to index.css minified. 
import './custom.scss';


const Test = () =>
{
  console.log("test called");  
  const [test, setTest] = React.useState('a'); 
  const [test2, setTest2] = React.useState('b'); 
   return  <div>
        <form>
            <input value={test} onChange={(e)=>setTest(e.target.value)} />
            <input value={test2} onChange={(e)=>setTest2(e.target.value)} />
        </form>
    </div>
}

registerBlockType( 'activities/activity-gap-fill', {
    apiVersion: 2,
    title: 'Activity Gap Fill',
    icon: 'universal-access-alt',
    category: 'design',

    edit: ( { setAttributes, attributes } ) => {
        
        const blockProps = useBlockProps();
        console.log("edit called", blockProps);
        return (
            <div { ...blockProps }>
                <Test></Test>
            </div>
        );
    },
 
  
    save() {
        return null;
    },
} );