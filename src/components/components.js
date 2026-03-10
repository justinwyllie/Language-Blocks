import Row from 'react-bootstrap/Row';
import Col from 'react-bootstrap/Col';
import Form from 'react-bootstrap/Form';
import { Field, ErrorMessage, FieldArray } from 'formik';
import { Alert, Button} from 'react-bootstrap';
import React from 'react';

import { useSelect, useDispatch }  from '@wordpress/data';
import { useEntityProp, store as coreStore } from '@wordpress/core-data';
import { PanelRow } from '@wordpress/components';
import { PluginDocumentSettingPanel } from '@wordpress/edit-post'; 
import { useState, useRef } from 'react';
import Overlay from 'react-bootstrap/Overlay';
import Tooltip from 'react-bootstrap/Tooltip';

const settings  = window.kea_language_blocks.settings;
import { LABELS } from '../translations';
import { CapitalizeFirstLetter } from "../helpers";

//import { useDispatch } from '@wordpress/data'; 
//import { store as coreStore } from '@wordpress/core-data';
//const { saveEditedEntityRecord } = useDispatch( coreStore );


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

const GapFillQuestion = ({idx, remove, handleChange, handleBlur, setFieldValue}) => {

 
        
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
                    onChange={handleChange}
                    onBlur={(e) => {
                        const trimmed = e.target.value.trim();
                        if (trimmed !== e.target.value) {
                            setFieldValue(
                                `questions[${idx}].answer`, 
                                trimmed
                            );
                        }
                        handleBlur(e);
                    }}
                />
                
                
                <div>
                    <p>
                        <label>
                            <Field 
                            type="radio" 
                            name={`questions.${idx}.matchingMode`} 
                            value="aligned"
                            />
                            Aligned (position-locked)
                        </label>
                        
                        <label>
                            <Field 
                            type="radio" 
                            name={`questions.${idx}.matchingMode`} 
                            value="independent"
                            />
                            Independent (any variant)
                        </label>
                    </p>
                </div>

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

const MultipleChoiceQuestionAnswer = ({idx, idx2}) => {

 
    let placeholder = "choice";
    if (idx2 == 0)
    {
        placeholder = placeholder + " " + idx2 + " (correct variant)";
    }
    else
    {
        placeholder = placeholder + " " + idx2 + " (incorrect variant)";
    }

    return(<Col md={6}>
                    <Field className="kea-wide-field kea-question-field mb-2" name={`questions[${idx}].answers[${idx2}]`}
                        placeholder={placeholder}
                        type="text"
                    />
                   
                    <ErrorMessage
                            name={`questions[${idx}].answers[${idx2}]`}
                            component="div"
                            className="field-error"
                    />
    </Col>)
}

const MultipleChoiceQuestion = ({idx, remove}) => {
    
    //validateOnChange={true}
    const counter  = [...Array(4).keys()];

    return (
    <div className="kea-additional-field-block"> 
        <Form.Group as={Row}>
            <Form.Label column md={2}>{idx + 1}. (question)</Form.Label>
            <Col md={10}>
                <Field className="kea-wide-field kea-additional-field" name={`questions[${idx}].question`}
                    placeholder="What did Einstein invent?"
                    type="text"
                />
               
                <ErrorMessage
                          name={`questions[${idx}].question`}
                          component="div"
                          className="field-error"
                />
            </Col>
        </Form.Group> 

        <Row>
            <Form.Label column md={12}>{idx + 1}. (answers:)</Form.Label>
        </Row>

        <Form.Group as={Row}>
        <FieldArray name="answers" >
            {({ insert, remove, push}) => (
                <div>     
                    {counter.map( (item, idx2) =>            
                        <MultipleChoiceQuestionAnswer 
                            idx={idx} 
                            idx2={idx2} 
                           
                            
                            >
                        </MultipleChoiceQuestionAnswer>)
                    }
                
                </div>
            )}
            </FieldArray>
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



const AuthorPanel = () => {

    //https://gist.github.com/5ally/a35935bbaa5b3d913dcac5f27e163cce
    //https://wordpress.stackexchange.com/questions/417512/get-the-email-of-the-author-of-the-currently-being-edited-post-in-gutenberg-fron/417513#417513
    let postAuthorId = wp.data.select( 'core/editor' ).getCurrentPostAttribute( 'author' );
  
    const {
		currentAuthorId
	} = useSelect(
		( select ) => ( {
			currentAuthorId: select( 'core/editor' ).getCurrentPostAttribute( 'author' ),
		
		} ),
		[]
	);

    const authorData = useSelect(
		( select ) => select( 'core' ).getUser( currentAuthorId ),
		[ currentAuthorId ]
	);

 
    return(<PluginDocumentSettingPanel title={ __( 'Creator', 'kea') } initialOpen="true">
			<PanelRow>
				<div>
                    <p className="kea-emp1">Email:</p> <p>{authorData?.email}</p>
                </div>
			</PanelRow>

	</PluginDocumentSettingPanel>);


}

const TipTool = (props) => {
    
    if (props.show)
    {
        return <div className="kea-tiptool"> 
                    <div>
                        {props.tip}
                    </div>
          </div>
    }
    else
    {
        return <></>
    }

}



const LinkPanel = () => {


  
    const [show1, setShow1] = useState(false);
    const [show2, setShow2] = useState(false);
    const target = useRef(null);
    const target2 = useRef(null);
    
    const [ meta, setMeta ] = useEntityProp( 'postType', 'kea_activity', 'meta' ); 


   function copyToClipboard(e, toggle)
   {
   
        toggle(true);
        var text = e.currentTarget.innerText;
        navigator.clipboard.writeText(text);
          
        setTimeout(() => {
      
            toggle(false);
            
        }, 800);
        
   }
    

    const postType = useSelect(
        ( select ) => select( 'core/editor' ).getCurrentPostType(),
        []
    );

  

    if (postType != "kea_activity")
    {
        return null;
    }
    

    //https://developer.wordpress.org/block-editor/reference-guides/data/data-core-editor/
    //useSelect monitors store value and if it changes will update slug causing rerender
    
    const slug = useSelect(
        ( select ) => select( 'core/editor' ).getEditedPostSlug()
    );
    //https://developer.wordpress.org/news/2024/03/28/how-to-work-effectively-with-the-useselect-hook/ TODO
    const postId = useSelect(
        ( select ) => select( 'core/editor' ).getCurrentPostId()
    );
    
    //persists
    const  updateAndSaveMeta = async (keyItem, newValue) => {
        const { saveEditedEntityRecord } = useDispatch( coreStore );
 
        setMeta( { ...meta, [keyItem]:  newValue} );
        /*
        this should be saved when the post is saved. 
        try {
            await  saveEditedEntityRecord('postType', 'kea_activity', postId);
        
        } catch (error) {
            console.error('Save failed:', error);
        }
        */
        
    };


   const getRandom = () =>
   {
        const arraySet = new Uint32Array(3);
        self.crypto.getRandomValues(arraySet);
        return arraySet;
    
   }
   
   

    let vals = getRandom();
    let linkWithKey;
    let linkWithoutKey;
    const saveMessage = __("Save Post to get link");

    if (    (! meta.hasOwnProperty("_with_key_meta"))  || (   meta.hasOwnProperty("_with_key_meta") &&
        ((meta._with_key_meta == '') || (typeof meta._with_key_meta == "undefined"))   )   )
    {
            updateAndSaveMeta("_with_key_meta", vals[0].toString());
            if (settings.domain.type == "query")
            {
                linkWithKey = settings.domain.domainForUsers + "/?q=" + slug + "&postId=" + postId + "&key=" + vals[0].toString();
            }
            else
            {
                linkWithKey = settings.domain.domainForUsers + "/" + slug + "/" + postId + "/" + vals[0].toString();
            }
          
    }
    else
    {   
        if (settings.domain.type == "query")
        {
            linkWithKey = settings.domain.domainForUsers + "/?q=" + slug + "&postId=" + postId + "&key=" + meta._with_key_meta;
        }
        else
        {
            linkWithKey = settings.domain.domainForUsers + "/" + slug + "/" + postId + "/" + meta._with_key_meta;
        }
    }

    if (    (! meta.hasOwnProperty("_without_key_meta"))  || (   meta.hasOwnProperty("_without_key_meta") &&
    ((meta._without_key_meta == '') || (typeof meta._without_key_meta == "undefined"))   )   )
    {

        updateAndSaveMeta("_without_key_meta", vals[1].toString());
           
        if (settings.domain.type == "query")
        {
            linkWithoutKey = settings.domain.domainForUsers + "/?q=" + slug + "&postId=" + postId + "&key=" + vals[1].toString();
        }
        else
        {
            linkWithoutKey = settings.domain.domainForUsers + "/" + slug + "/" + postId + "/" + vals[1].toString();
        }
        
    }
    else
    {   
        if (settings.domain.type == "query")
        {
            linkWithoutKey = settings.domain.domainForUsers + "/?q=" + slug + "&postId=" + postId + "&key=" + meta._without_key_meta;
        }
        else
        {
            linkWithoutKey = settings.domain.domainForUsers + "/" + slug + "/" + postId + "/" + meta._without_key_meta;
        }
    
    }

    if (    (! meta.hasOwnProperty("_assignment_key_meta"))  || (   meta.hasOwnProperty("_assignment_key_meta") &&
    ((meta._assignment_key_meta == '') || (typeof meta._assignment_key_meta == "undefined"))   )   )
    {
        updateAndSaveMeta("_assignment_key_meta", vals[2].toString());
    }


 
//linkWithoutKey = meta._link_for_assigments;
    

    /*
    const { editPost } = useDispatch( 'core/editor', [ postMeta._kea_vocab_item_ru_meta, 
        postMeta._kea_vocab_item_phrase_meta ] );
    */
        //https://developer.wordpress.org/block-editor/reference-guides/slotfills/plugin-document-setting-panel/
        return(<PluginDocumentSettingPanel title={ __( 'Links', 'kea') } initialOpen="true">
			<PanelRow>
          
				<div className="">
                    <p className="kea-emp1">{ __( 'Exercise with Key:', 'kea' ) }</p>
                  
                    <p ref={target}
                        onClick={(e) => copyToClipboard(e, setShow1)} className="">{linkWithKey} <span  className="kea-pointer dashicons-before dashicons-admin-page"></span> 
                    </p>
          
                    <Overlay target={target.current} show={show1} placement="left">
                        {(props) => (
                        <Tooltip  {...props}>
                                {CapitalizeFirstLetter(LABELS[settings.defaultUserLang]['copied_to_clipboard']['nominative'])} 

                        </Tooltip>
                        )}
                    </Overlay>
                  
                </div>
			</PanelRow>
            <PanelRow>
				<div className="">
                    <p className="kea-emp1">{ __( 'Exercise without Key:', 'kea' ) }</p>
                    
                            
                    <p  ref={target2}
                        onClick={(e) => copyToClipboard(e, setShow2)} className="">{linkWithoutKey}  <span className="kea-pointer dashicons-before dashicons-admin-page"></span> </p>

                    <Overlay target={target2.current} show={show2} placement="left">
                        {(props) => (
                        <Tooltip  {...props}>
                                {CapitalizeFirstLetter(LABELS[settings.defaultUserLang]['copied_to_clipboard']['nominative'])} 

                        </Tooltip>
                        )}
                    </Overlay>
                   
                </div>		
				
			</PanelRow>


		</PluginDocumentSettingPanel>);
}

// <Button onClick={() => setShow(true)}>Show Alert</Button>

const InfoBoxAlignment = () =>
{
    const [show, setShow] = useState(true);
  
    return (
      <div className="container mt-3">
        {show ? (
          <Alert variant="info" onClose={() => setShow(false)} dismissible>
                <p>
                    Nb. if you choose 'Independent (any variant)' the system will not try to mark based on variant position. Usually, this is not what you want - so 
                    in almost all cases simply leave this setting at the default 'Aligned (position locked)'. The 'Independent (any variant)' setting is useful 
                    for a case where the variants are independent, for example:
                </p>
                <p>
                    Have:Did|seen:see|am cooking:'m cooking|
                </p>
                <p>
                In this case you probably want to choose 'Independent (any variant)' so that regardless of what the user chose for the first two 
                sections | either of the third variants 'am cooking:'m cooking' will be allowed. 
                </p>
          </Alert>
        ) : (
            <span></span>
        )}
      </div>
    );
}

export {
    Instruction,
    GapFillQuestion,
    LinkPanel,
    AuthorPanel,
    MultipleChoiceQuestion,
    InfoBoxAlignment
}
