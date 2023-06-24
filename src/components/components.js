import Row from 'react-bootstrap/Row';
import Col from 'react-bootstrap/Col';
import Form from 'react-bootstrap/Form';
import { Field, ErrorMessage } from 'formik';
import { Button} from 'react-bootstrap';

import { useSelect, useDispatch }  from '@wordpress/data';
import { useEntityProp } from '@wordpress/core-data';
import { select, subscribe } from '@wordpress/data';
import { TextControl, TextareaControl, PanelRow } from '@wordpress/components';
import { PluginDocumentSettingPanel } from '@wordpress/edit-post'; 
import { settings } from "../constants";



const { __ } = wp.i18n; //TODO check


const Instruction = ({instruction, idx}) => {
        
    return (
    <Row>
        <Col md={2}>
            <span className="badge bg-secondary">{instruction.lang}</span>
        </Col>    
        <Col md={10}>
            <Field as="textarea"  className="kea-wide-field"
                name={`instructions.${idx}.text`} rows={3}
            >
            </Field>
            <ErrorMessage
                          name={`instructions.${idx}.text`}
                          component="div"
                          className="field-error"
            />
        </Col>
    </Row>)
}

const GapFillQuestion = ({idx, remove}) => {
        
    return (
    <div className="kea-additional-field-block"> 
        <Form.Group as={Row}>
            <Form.Label column md={2}>{idx + 1}. (question)</Form.Label>
            <Col md={10}>
                <Field className="kea-wide-field kea-additional-field" name={`questions.${idx}.question`}
                    placeholder="Example ___ sentence with blank and (keyword)"
                    type="text"
                />
               
                <ErrorMessage
                          name={`questions.${idx}.question`}
                          component="div"
                          className="field-error"
                />
            </Col>
        </Form.Group> 
        <Form.Group as={Row}>
            <Form.Label column md={2}>{idx + 1}. (answer)</Form.Label>
            <Col md={10}>
                <Field className="kea-wide-field kea-question-field" name={`questions.${idx}.answer`}
                    placeholder="keywordAnswer"
                    type="text"
                />
                <ErrorMessage
                          name={`questions.${idx}.answer`}
                          component="div"
                          className="field-error"
                />
            </Col>
        </Form.Group>
        <Row>
            <Col className="text-right">
                <Button 
                    type="button"
                    className="secondary"
                    onClick={() => remove(idx)}>-</Button>   
            </Col>
        </Row> 
    </div>)
}




const LinkPanel = () => {

    
   
    

    const postType = useSelect(
        ( select ) => select( 'core/editor' ).getCurrentPostType(),
        []
    );

  

    if (postType != "activity_gap_fill")
    {
        return null;
    }
    

    //https://developer.wordpress.org/block-editor/reference-guides/data/data-core-editor/
    //useSelect monitors store value and if it changes will update sluh causing rerender
    const slug = useSelect(
        ( select ) => select( 'core/editor' ).getEditedPostSlug()
    );
    
    const postId = useSelect(
        ( select ) => select( 'core/editor' ).getCurrentPostId()
    );

    const [ meta, setMeta ] = useEntityProp( 'postType', 'activity_gap_fill', 'meta' ); 
    console.log("meta", meta);

    let withKeyMeta;
    let withoutKeyMeta; 

    withKeyMeta = meta._with_key_gap_fill_meta;
    withoutKeyMeta = meta._without_key_gap_fill_meta;

   let getRandom = () =>
   {
        const arraySet = new Uint32Array(2);
        self.crypto.getRandomValues(arraySet);
        return arraySet;
    
   }


   if ( ((withKeyMeta == '') || (withKeyMeta == undefined)) || ((withoutKeyMeta == '') || (withoutKeyMeta == undefined))) 
   {

        let vals = getRandom();

        if ((withKeyMeta == '') || (withKeyMeta == undefined))
        {
                withKeyMeta =  vals[0];
                setMeta( { ...meta, _with_key_gap_fill_meta: withKeyMeta.toString() } );
        }

        if ((withoutKeyMeta == '') || (withoutKeyMeta == undefined))
        {
                withoutKeyMeta = vals[1];
                setMeta( { ...meta, _without_key_gap_fill_meta:  withoutKeyMeta.toString() } );
        }
    
    }

   

    let linkWithKey = settings.domainForUsers + "/" + slug + "/" + postId + "?key=" + withKeyMeta;
    let linkWithOutKey = settings.domainForUsers + slug + "/" + postId + "?key=" + withoutKeyMeta;

  

    /*
    const { editPost } = useDispatch( 'core/editor', [ postMeta._kea_vocab_item_ru_meta, 
        postMeta._kea_vocab_item_phrase_meta ] );
    */
        //https://developer.wordpress.org/block-editor/reference-guides/slotfills/plugin-document-setting-panel/
        return(<PluginDocumentSettingPanel title={ __( 'Links', 'kea') } initialOpen="true">
			<PanelRow>
				<div className="">
                    <p className="kea-emp1">{ __( 'Exercise with Key:', 'kea' ) }</p>
                    <p className="">{linkWithKey}</p>
                </div>
			</PanelRow>
            <PanelRow>
				<div className="">
                    <p className="kea-emp1">{ __( 'Exercise without Key:', 'kea' ) }</p>
                    <p className="">{linkWithOutKey}</p>
                </div>		
				
			</PanelRow>


		</PluginDocumentSettingPanel>);
}

export {
    Instruction,
    GapFillQuestion,
    LinkPanel
}
