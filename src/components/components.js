import Row from 'react-bootstrap/Row';
import Col from 'react-bootstrap/Col';
import Form from 'react-bootstrap/Form';
import { Field, ErrorMessage } from 'formik';
import { Button} from 'react-bootstrap';

import { useSelect, useDispatch }  from '@wordpress/data';
import { select, subscribe } from '@wordpress/data';
import { TextControl, TextareaControl, PanelRow } from '@wordpress/components';
import { PluginDocumentSettingPanel } from '@wordpress/edit-post'; 


const { PluginSidebar } = wp.editPost;
const { PanelBody } = wp.components; 
const { isSavingPost } = select( 'core/editor' );
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

const LinkPanelWrapper = () =>
{



     //https://github.com/WordPress/gutenberg/issues/17632#issuecomment-583772895 
     let checked = true; // Start in a checked state.
     subscribe( () => {
         //console.log("subscribe1");
         if ( isSavingPost() ) {
              checked = false;
         } else {
              if ( ! checked ) {
                 checkPostAfterSave(); // Perform your custom handling here.
                 checked = true;
             }
     
         }
     } );
     let slugFromDataStore;
     const checkPostAfterSave = () =>
     {
    
         //gets the last saved state
         let postData = wp.data.select("core/editor").getCurrentPost(); 
         console.log("postData1.slug", postData.slug );
         if ((postData !== undefined) && (postData.hasOwnProperty("slug")))
         {
             slugFromDataStore = postData.slug;
             console.log("slugFromDataStore",slugFromDataStore);
             return(<LinkPanel slugFromDataStore="sssds" /> )
         }
     }


     return(<LinkPanel slugFromDataStore="sssds" /> )
         
}


const LinkPanel = (props) => {

    console.log("porps", props);

    const postType = useSelect(
        ( select ) => select( 'core/editor' ).getCurrentPostType(),
        []
    );

    console.log("postType1" , postType );

    if (postType != "activity_gap_fill")
    {
        return null;
    }

    console.log("postType1b" , postType );
    
   
   
    

    const slug = useSelect(
        ( select ) => select( 'core/editor' ).getEditedPostSlug()
    );
    
    console.log("slug1", slug);
    //slug1 should always == postData.slug


    /*
    const { editPost } = useDispatch( 'core/editor', [ postMeta._kea_vocab_item_ru_meta, 
        postMeta._kea_vocab_item_phrase_meta ] );
    */

        return(<PluginDocumentSettingPanel title={ __( 'Links', 'kea') } initialOpen="true">
			<PanelRow>
				<TextControl
					label={ __( 'Exercise with Key', 'kea' ) }
					value={slug}
                   				
				/>  
			</PanelRow>
            <PanelRow>
				<TextControl
					label={ __( 'Exercise without Key', 'kea' ) }
					value={props.slugFromDataStore? props.slugFromDataStore : ''}
                   				
				/>  
			</PanelRow>


		</PluginDocumentSettingPanel>);
}

export {
    Instruction,
    GapFillQuestion,
    LinkPanelWrapper
}
