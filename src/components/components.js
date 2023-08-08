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
import { useState, useRef } from 'react';
import Overlay from 'react-bootstrap/Overlay';
import Tooltip from 'react-bootstrap/Tooltip';

import { LABELS, settings } from '../constants';
import { CapitalizeFirstLetter } from "../helpers";




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
    console.log("tiptool", props.show);
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

  

    if (postType != "activity_gap_fill")
    {
        return null;
    }
    

    //https://developer.wordpress.org/block-editor/reference-guides/data/data-core-editor/
    //useSelect monitors store value and if it changes will update slug causing rerender
    //this is reliable. the issue is if user manually updates it this will invalidate old slugs and old links will be invalidated
    //so - unless we block that - and you can't just hide the slug part only the whole panel - it is best maybe not to rely on slug?
    const slug = useSelect(
        ( select ) => select( 'core/editor' ).getEditedPostSlug()
    );
    
    const postId = useSelect(
        ( select ) => select( 'core/editor' ).getCurrentPostId()
    );

  


   let getRandom = () =>
   {
        const arraySet = new Uint32Array(2);
        self.crypto.getRandomValues(arraySet);
        return arraySet;
    
   }

   const [ meta, setMeta ] = useEntityProp( 'postType', 'activity_gap_fill', 'meta' ); 
    
   console.log("meta", meta);



        //TDDO - not sure about the structure of this - will we always have meta by this point if it exists?
        //or do i need to useSelect?
    if ( ((meta._with_key_gap_fill_meta == '') || (meta._with_key_gap_fill_meta == undefined)) 
        || ((meta._without_key_gap_fill_meta == '') || (meta._without_key_gap_fill_meta == undefined))) 
    {

            let vals = getRandom();

            if ((meta._with_key_gap_fill_meta == '') || (meta._with_key_gap_fill_meta == undefined))
            {
                    
                    setMeta( { ...meta, _with_key_gap_fill_meta: vals[0].toString()} );
            }

            if ((meta._without_key_gap_fill_meta == '') || (meta._without_key_gap_fill_meta == undefined))
            {
                    
                    setMeta( { ...meta, _without_key_gap_fill_meta:  vals[1].toString() } );
            }
        
    }

  

  

    let linkWithKey;
    let linkWithoutKey;
    if (settings.domain.type == "query")
    {
        linkWithKey = settings.domain.domainForUsers + "/?q=" + slug + "&postId=" + postId + "&key=" + meta._with_key_gap_fill_meta;
        linkWithoutKey = settings.domain.domainForUsers + "/?q=" + slug + "&postId=" + postId + "&key=" + meta._without_key_gap_fill_meta;
    }
    else
    {
        linkWithKey = settings.domain.domainForUsers + "/" + slug + "/" + postId + "/" + meta._with_key_gap_fill_meta;
        linkWithoutKey = settings.domain.domainForUsers + "/" + slug + "/" + postId + "/" + meta._without_key_gap_fill_meta;
    }



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

export {
    Instruction,
    GapFillQuestion,
    LinkPanel,
    AuthorPanel
}
