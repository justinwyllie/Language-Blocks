import Form from 'react-bootstrap/Form';
import Row from 'react-bootstrap/Row';
import Col from 'react-bootstrap/Col';
import { useSelect } from '@wordpress/data';
import { useEntityProp } from '@wordpress/core-data';
import { useRef } from '@wordpress/element';
import { Formik, FieldArray, useFormikContext  } from 'formik';
import { Instruction, GapFillQuestion, LinkPanel, AuthorPanel} from './components';
import { Alert } from 'react-bootstrap';

import { useBlockProps, RichText } from '@wordpress/block-editor'; 
const settings  = window.kea_language_blocks.settings;
import { LABELS } from '../translations';





import { useDispatch } from '@wordpress/data';
import { store as editorStore } from '@wordpress/editor'; // ← Missing import
const POST_TITLE_LENGTH = window.kea_language_blocks.settings.page_title_length;



const GapFill = ({postType, setAttributes, attributes}) =>
{
    const [ meta, setMeta ] = useEntityProp( 'postType', postType, 'meta' ); 
    const initialValues = {}; 
    initialValues.set = false;
    const defaultLang = 'en';
    const additionalLangs = ['ru'];
    const supportedLangs = [defaultLang, ...additionalLangs];
  
console.log("ATTRIBUTES ARE", attributes);
  

    const { lockPostSaving, unlockPostSaving } = useDispatch(editorStore);

    const DataLifter = ({ setAttributes }) =>
    {
        //every time form renders which s every time a value changes 
        const { values, errors } = useFormikContext();
        console.log("errors IS2", errors);
      
        if (Object.keys(errors).length > 0)
        {
            lockPostSaving('activities/activity-gap-fill');
        }
        else
        {
            setAttributes({formData: values});   
            setAttributes({ activityType: 'gapfill'});
            unlockPostSaving('activities/activity-gap-fill');
        }


    }

   
    const { postTitle } = useSelect(
        ( select ) => ( {
            postTitle: select('core/editor').getEditedPostAttribute('title')
        } ),
        []
    );
    const alertRef = useRef();


    //see increase_grammar_terms_per_page_limit PHP
    const grammarTaxonomy =  useSelect(
        ( select ) => wp.data.select('core').getEntityRecords('taxonomy', "grammar", {per_page: 1000, context: "view", call: 'kea'}) 

    ); 

   const russianGrammarTaxonomy =  useSelect(
        ( select ) => wp.data.select('core').getEntityRecords('taxonomy', "russian_grammar", {per_page: 1000, context: "view", call: 'kea'})
                 
    );
    
   
    
    const terms = [];
    let userLabels = [];
 

    if (grammarTaxonomy) {
        grammarTaxonomy.forEach((item => {
            terms[item.id] = item.name;
        }));
    }

    if (russianGrammarTaxonomy) {
        russianGrammarTaxonomy.forEach((item => {
            terms[item.id] = item.name;
        }))
    }


    //detect user changing taxonomy terms
    //i think this will get the latest unsaved values   - it subscribes
    //just gets you ids though
    const [grammarTerms, setGammarTerms] = useEntityProp( 'postType', postType, 'grammar_terms' ); 
    const [russianGrammarTerms, setRussianGrammarTerms] = useEntityProp( 'postType', postType, 'russian_grammar_terms' ); 
    
    
    grammarTerms.forEach((item) => {
        userLabels.push(terms[item]);
    });
    russianGrammarTerms.forEach((item) => {
        userLabels.push(terms[item]);
    });
   

     
    const blockProps = useBlockProps();//? gets props passed to this 'edit' component?

    const ErrorMessage = ({postTitle}) =>
    {
        const { isValid, errors } = useFormikContext();

       

        if (isValid && (postTitle.length >= POST_TITLE_LENGTH))
        {   
            return <></>
        }
        else
        {
            if (postTitle.length < POST_TITLE_LENGTH)
            {
                alertRef.current?.closest(".block-editor-writing-flow").scrollIntoView({ behavior: 'smooth' }); //TODO maybe hanlde no element found?
            }
            return <Alert variant="danger">{LABELS[settings.defaultUserLang]['please_check_the_form']['nominative']}</Alert>;
        }
    };

    function setInitialValues() {

        console.log("INIT ATTR", attributes);
            
            if (Object.keys(attributes['formData']).length === 0)
            {
                initialValues.legacyName = '';
                initialValues.models = '';
                initialValues.explanation = '';
                initialValues.questions = [];
                initialValues.instructions = [];
                for (const lang of supportedLangs)
                {
                    initialValues.instructions.push({lang: lang, text: ''});
                }
            }
            else 
            {

                const currentFormData = attributes['formData'];
                for (var field in currentFormData)
                {
                    initialValues[field] = currentFormData[field];
                }
            }

            initialValues.set = true;
        }



        if (!initialValues.set)
        {
            setInitialValues();
        }
        

    return <div ref={alertRef}>
        {(postTitle.length < POST_TITLE_LENGTH) ? <div className="field-error">{LABELS[settings.defaultUserLang]['minimum_length_title']['nominative']}</div> : ''}
        <Formik
            initialValues={initialValues}
            

            validate={values => {
                console.log("VALIDATE CALLED");
                let errors = {};
            
                /*
                if (values.ageGroup == 0) {
                    errors.ageGroup = "Required"; 
                }
                if (values.level == 0) {
                    errors.level = "Required"; 
                }
                */

                //TODO could valdiate for correct format ___ and |
            
                //https://formik.org/docs/guides/arrays
                //todo how to do my array of questions https://formik.org/docs/api/fieldarray

                if (settings.site == "repititor")
                {
                    let instructionsError = true;
                    let instructionsCount = 0;
                    values.instructions.forEach((item, idx) =>
                    {
                        
                        if (item.text != '')
                        {
                            instructionsError = false;
                        }

                        instructionsCount = idx;
                    });

                    if (instructionsError) {
                        errors.instructions = new Array();
                        errors.instructions[instructionsCount] = {"lang": '', "text": "At least one language must have instructions"};
                    }
                }
                else
                {
                    values.instructions.forEach((item, idx) =>
                    {
                        if (item.lang == defaultLang)
                        {
                            if (item.text == '')
                            {
                                errors.instructions = new Array();
                                errors.instructions[idx] = {"lang": '', "text": "Required"};
                            }
                        }
                    });

                }
            
                
                
                values.questions.forEach((item, idx) =>
                {
                    let errorObj = {"question": '', "answer": ''};

                    if ((values.questions[idx].question == '') 
                        || (!values.questions[idx].question.includes("___")))
                    {
                        if (errors.questions == undefined)
                        {
                            errors.questions = new Array();
                        }
                        errors.questions[idx] = errorObj;
                        errors.questions[idx].question = "Required and must contain ___";
                    }

                    //for formik to pass validation there must be no
                    //questions field on the errors object at all. 
                    //so only put it on if there is at least one error
                    //test for: exists, has at least one |, and count of | = count of ___
                    //if values.questions[idx].question.match(/___/g) is null we won't go into error
                    //but this case will have been picked up above. the purpose of this test
                    //is to avoid comparing lengths if we don't have arrays
                    if (   (values.questions[idx].answer == '') 
                        || (values.questions[idx].answer.match(/\|/g) == null )
                        || (values.questions[idx].question.match(/___/g) != null &&
                            (values.questions[idx].answer.match(/\|/g).length !=  
                            values.questions[idx].question.match(/___/g).length)
                            )
                        )
                    {
                        if (errors.questions == undefined)
                        {
                            errors.questions = new Array();
                        }
                        if (errors.questions[idx] == undefined)
                        {
                            errors.questions[idx] = errorObj;    
                        }
                        errors.questions[idx].answer = "Required and number of | must equal number of ___";
                    }
                })
                
                

                return errors;
            }}
            
            onSubmit={(values, errors) => {
       
                
            }}
                
        >

            {({
                values,
                errors,
                touched,
                handleChange,
                setFieldValue,
                handleBlur,
                handleSubmit,
                isSubmitting,
        
            }) => (
            
     

            <Form onSubmit={handleSubmit} name="activty" id="activity" className="">
                
                <ErrorMessage postTitle={postTitle}></ErrorMessage>

                <DataLifter  setAttributes={setAttributes} />
    

                <Form.Group as={Row}>
                    <Form.Label column sm={2}>Legacy Name (optional)</Form.Label>
                    <Col md={10}>
                        <Form.Control md={10} name="legacyName" id="legacyName"
                            handleChange={handleChange}
                            onBlur={handleBlur}
                            value={values.legacyName}
                            isInvalid={!!errors.legacyName && !!touched.legacyName}
                        ></Form.Control>
                        {errors.legacyName && touched.legacyName ? 
                            <div className="invalid-feedback">
                            {errors.legacyName}
                            </div> : null
                        }
                    </Col>
                </Form.Group>

                

                <Form.Group as={Row}> 
                        <Col>
                            <h3>Instructions</h3>
                        </Col>
                </Form.Group>        

                <div>     
                    {values.instructions.length > 0 && values.instructions.map((instruction, idx) =>           
                                <Instruction instruction={instruction} idx={idx} />
                    )}     
                </div>

                <Form.Group as={Row}> 
                        <Col>
                            <h3>Models</h3>
                        </Col>
                </Form.Group>   

                <Form.Group as={Row}>
                    <Col md={2}>
                        
                    </Col>
                    <Col md={10}>
                        
                        
                        <RichText name="models" id="models"
                            className="rich-input-control mt-3"
                            tagName="div" 
                            value={ values.models } 
                            allowedFormats={ [ 'core/bold', 'core/italic' ] } 
                            onChange={ ( content ) => {
                                setFieldValue("models", content);
                            } } 
                            
                        />

                    </Col>
                </Form.Group>

                <Form.Group as={Row}> 
                        <Col>
                            <h3>Explanation</h3>
                        </Col>
                </Form.Group>   

                <Form.Group as={Row}>
                    <Col md={2}>
                        
                    </Col>
                    <Col md={10}>
                        
                        
                        <RichText name="explanation" id="explanation"
                            className="rich-input-control mt-3"
                            tagName="div" 
                            value={ values.explanation } 
                            allowedFormats={ [ 'core/bold', 'core/italic' ] } 
                            onChange={ ( content ) => {
                                setFieldValue("explanation", content);
                            } } 
                            
                        />

                    </Col>
                </Form.Group>



                <Row>
                    <Col>
                        <h3>Questions</h3>
                        <p>To create a gap use ___ (3 underscores). Words in brackets separated by a comma.
                            Answers in second box separated by |. Variants can be expressed like this: is not:isn't|</p>
                            <p class="mb-4">
                                In sentences with more than one gap variants must be consistent. For example:<br />
                                Did:Have|take:taken (correct)<br />
                                Did:Have|taken:take (not correct - wrong order)<br />
                                Have:Did|put| (not correct - number of variants must match)<br />
                                Have:Did|cut:cut| (correct - note; you have to repeat the variants for consistency)
                            
                            </p>

                    </Col>
                </Row>

                <FieldArray name="questions" validateOnChange={true}> 
                {({ insert, remove, push }) => (
                    <div>     
                        {values.questions.length > 0 && values.questions.map( (item, idx) =>            
                            <GapFillQuestion idx={idx} 
                                insert={insert}
                                remove={remove}
                                values={values} 
                                errors={errors} touched={touched} 
                                handleChange={handleChange}
                                setFieldValue={setFieldValue} 
                                handleBlur={handleBlur} 
                                >
                            </GapFillQuestion>)
                        }
                        <div className="text-right margin-top-10">
                            <button
                                className="secondary btn btn-primary"
                                type="button"
                                onClick={() => push({ question: '', answer: '' })}>
                            +
                            </button>
                        </div>
                    </div>
                )}
                </FieldArray>
                
                <Form.Group as={Row}>
                    <Col md={12}>
                            {/*<Button onClick={() => addQuestion() }>+</Button>*/}
                    </Col>
                </Form.Group>

                <Form.Group as={Row}>
                    <Col sm={{ span: 10, offset: 0 }}>
                        <div className="px-1 py-1 mt-3 mb-3">
                            {userLabels.map((item, i) => {
                                return <span className="badge rounded-pill bg-info text-dark me-2" key={i}>{item}</span>
                            })}
                        </div>
                    </Col>
                </Form.Group> 

                
            </Form>
            )}
        </Formik>
    

</div>
    
}

/*
    <Form.Group as={Row}>
        <Col md={12}>
        
            <Form.Label>Activity XML</Form.Label>
            <Form.Control as="textarea" id="rawXML" name="rawXML" rows={6}
                readOnly value={ values.rawXML }></Form.Control>
        
        </Col>
    </Form.Group>
*/
            



export {
    GapFill
}
